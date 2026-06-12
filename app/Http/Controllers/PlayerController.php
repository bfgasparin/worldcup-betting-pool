<?php

namespace App\Http\Controllers;

use App\Http\Requests\Manage\SetPlayerEmailRequest;
use App\Http\Requests\Manage\StorePlayerRequest;
use App\Http\Requests\Manage\UpdatePlayerRequest;
use App\Models\Entry;
use App\Models\Pool;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Admin pre-registration of players — the web counterpart of the user:pre-register and
 * user:set-email console commands. Admins create email-less players (so they can be joined to
 * pools before launch), edit them, and finally set a login email. Setting the email hands the
 * account to the player: from then on it is fully locked to admin editing {@see assertEditable()}.
 */
class PlayerController extends Controller
{
    /**
     * The roster of players, newest first, with their pool memberships and a search box.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('q', ''));

        $players = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        // Pools per player come from the Entry side: User has no entries() relation by convention.
        $poolsByUser = Entry::query()
            ->with('pool.tournament')
            ->whereIn('user_id', $players->getCollection()->pluck('id'))
            ->get()
            ->groupBy('user_id');

        $players->through(fn (User $user): array => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
            'locked' => $user->email !== null,
            'pools' => $this->poolSummaries($poolsByUser->get($user->id)),
        ]);

        return Inertia::render('manage/players', [
            'players' => $players,
            'pools' => $this->joinablePools(),
            'supportedLocales' => config('app.supported_locales'),
            'filters' => ['q' => $search],
        ]);
    }

    /**
     * Pre-register a player from a name, with no email yet, optionally joining pools.
     */
    public function store(StorePlayerRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $email = $request->validated('email');

            // forceCreate mirrors the user:pre-register command. With no email, both stay NULL and
            // the player stays editable until one is set. With an email, it's vouched for (verified
            // now, like user:set-email) — which immediately locks the account to admin edits.
            $user = User::forceCreate([
                'name' => $request->validated('name'),
                'locale' => $request->validated('locale'),
                'email' => $email,
                'email_verified_at' => $email !== null ? now() : null,
            ]);

            $this->joinPools($user, $request->poolModels());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player pre-registered.')]);

        return to_route('manage.players.index');
    }

    /**
     * The edit surface. Locked players are rendered read-only so the lock is visible to the admin.
     */
    public function edit(User $user): Response
    {
        $entries = Entry::query()
            ->with('pool.tournament')
            ->where('user_id', $user->id)
            ->get();

        return Inertia::render('manage/player-edit', [
            'player' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'locked' => $user->email !== null,
                'pools' => $this->poolSummaries($entries),
            ],
            'pools' => $this->joinablePools(),
            'supportedLocales' => config('app.supported_locales'),
        ]);
    }

    /**
     * Update an unlocked player's details and add them to more pools (add-only — never removed).
     */
    public function update(UpdatePlayerRequest $request, User $user): RedirectResponse
    {
        $this->assertEditable($user);

        DB::transaction(function () use ($request, $user): void {
            $user->fill([
                'name' => $request->validated('name'),
                'locale' => $request->validated('locale'),
            ])->save();

            $this->joinPools($user, $request->poolModels());
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Player updated.')]);

        return to_route('manage.players.edit', $user);
    }

    /**
     * Set the player's login email and vouch for it (verified immediately, mirroring user:set-email).
     * This is the one-way door that fully locks the account to the player.
     */
    public function setEmail(SetPlayerEmailRequest $request, User $user): RedirectResponse
    {
        $this->assertEditable($user);

        $user->forceFill([
            'email' => $request->validated('email'),
            'email_verified_at' => now(),
        ])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Login email set — the player can now sign in.')]);

        return to_route('manage.players.edit', $user);
    }

    /**
     * The authoritative full-lock guard: once a player has a login email, the account belongs to
     * them and admins can no longer touch it.
     */
    private function assertEditable(User $user): void
    {
        abort_unless($user->email === null, 403);
    }

    /**
     * Idempotently join the player to each pool, deliberately WITHOUT the PlayerJoinedPoolNotification
     * the web join sends — these are already-paid players the organizer is onboarding.
     *
     * @param  Collection<int, Pool>  $pools
     */
    private function joinPools(User $user, $pools): void
    {
        foreach ($pools as $pool) {
            $pool->entries()->firstOrCreate(['user_id' => $user->id]);
        }
    }

    /**
     * All pools still inside their prediction window, grouped-ready for the join selector.
     *
     * @return array<int, array{id: int, name: string, tournament_name: string}>
     */
    private function joinablePools(): array
    {
        return Pool::query()
            ->with('tournament')
            ->get()
            ->filter(fn (Pool $pool): bool => $pool->acceptsPredictions())
            ->map(fn (Pool $pool): array => [
                'id' => $pool->id,
                'name' => $pool->name,
                'tournament_name' => $pool->tournament->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Shape a player's entries into the pool chips the UI shows.
     *
     * @param  Collection<int, Entry>|null  $entries
     * @return array<int, array{id: int, name: string, tournament_name: string}>
     */
    private function poolSummaries($entries): array
    {
        if ($entries === null) {
            return [];
        }

        return $entries
            ->map(fn (Entry $entry): array => [
                'id' => $entry->pool->id,
                'name' => $entry->pool->name,
                'tournament_name' => $entry->pool->tournament->name,
            ])
            ->values()
            ->all();
    }
}
