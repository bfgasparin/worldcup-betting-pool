<?php

namespace Tests\Feature\Manage;

use App\Models\Fixture;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class EntryImportControllerTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
    }

    public function test_non_admins_cannot_access_any_backfill_endpoint(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('manage.backfill.create', $this->tournament))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('manage.backfill.preview', $this->tournament))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->post(route('manage.backfill.commit', $this->tournament))
            ->assertForbidden();
    }

    public function test_the_create_screen_lists_only_upfront_pools(): void
    {
        $this->actingAs($this->admin())
            ->get(route('manage.backfill.create', $this->tournament))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/backfill')
                ->has('pools', 1)
                ->where('pools.0.slug', 'world-cup-2026-ffa')
                ->has('pools.0.scoring_label')
                ->has('users')
                ->has('users.0.avatar')
            );
    }

    public function test_preview_auto_creates_the_entry_without_notifying_and_renders_flags(): void
    {
        Notification::fake();
        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.preview', $this->tournament), [
                'pool_id' => $this->pool->id,
                'user_id' => $user->id,
                'json' => json_encode($this->groupBlob()),
            ])
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('manage/backfill-review')
                ->where('preview.has_errors', false)
                ->where('user.id', $user->id)
                ->has('preview.rows')
            );

        $this->assertDatabaseHas('entries', ['pool_id' => $this->pool->id, 'user_id' => $user->id]);
        Notification::assertNothingSent();

        // The preview must persist no predictions.
        $entry = $this->pool->entryFor($user);
        $this->assertSame(0, $entry->groupPredictions()->count());
    }

    public function test_preview_rejects_a_phased_pool(): void
    {
        $phased = $this->tournament->pools()->where('slug', 'world-cup-2026-brothers')->firstOrFail();

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.preview', $this->tournament), [
                'pool_id' => $phased->id,
                'user_id' => User::factory()->create()->id,
                'json' => json_encode($this->groupBlob()),
            ])
            ->assertSessionHasErrors('pool_id');
    }

    public function test_preview_rejects_a_pool_from_another_tournament(): void
    {
        $otherPool = Pool::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.preview', $this->tournament), [
                'pool_id' => $otherPool->id,
                'user_id' => User::factory()->create()->id,
                'json' => json_encode($this->groupBlob()),
            ])
            ->assertSessionHasErrors('pool_id');
    }

    public function test_commit_writes_predictions_and_re_scores_the_pool(): void
    {
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $user = User::factory()->create();

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.commit', $this->tournament), $this->groupCommitPayload($user))
            ->assertRedirect(route('manage.backfill.create', $this->tournament));

        $entry = $this->pool->entryFor($user);
        $this->assertSame(72, $entry->groupPredictions()->count());
        $this->assertNotNull($entry->refresh()->total_points);
        $this->assertGreaterThan(0, $entry->total_points);
    }

    public function test_commit_is_blocked_for_a_populated_entry_without_overwrite(): void
    {
        $user = User::factory()->create();
        $entry = $this->pool->entries()->create(['user_id' => $user->id]);
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $payload = $this->groupCommitPayload($user);

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.commit', $this->tournament), $payload)
            ->assertSessionHasErrors('overwrite');

        // Only the 6 fixtures of group A remain — nothing was overwritten.
        $this->assertSame(6, $entry->groupPredictions()->count());
    }

    public function test_commit_overwrites_a_populated_entry_when_confirmed(): void
    {
        $user = User::factory()->create();
        $entry = $this->pool->entries()->create(['user_id' => $user->id]);
        $this->predictGroup($entry, $this->tournament, 'A', $this->seedOrderScores());

        $payload = $this->groupCommitPayload($user);
        $payload['overwrite'] = true;

        $this->actingAs($this->admin())
            ->post(route('manage.backfill.commit', $this->tournament), $payload)
            ->assertRedirect();

        $this->assertSame(72, $entry->groupPredictions()->count());
    }

    /**
     * A group-only backfill blob (the importer derives the knockout bracket), built from the seeded
     * fixtures with a seed-order scoreline.
     *
     * @return array<string, mixed>
     */
    private function groupBlob(): array
    {
        $matches = [];

        foreach ($this->groupFixtures() as $fixture) {
            [$home, $away] = $this->seedOrderScores()(
                $fixture->positions['home'],
                $fixture->positions['away'],
            );

            $matches[] = [
                'match_number' => $fixture->match_number,
                'home_team' => $fixture->homeTeam->code,
                'away_team' => $fixture->awayTeam->code,
                'home_goals' => $home,
                'away_goals' => $away,
            ];
        }

        return ['matches' => $matches];
    }

    /**
     * The commit POST body for a group-only backfill.
     *
     * @return array<string, mixed>
     */
    private function groupCommitPayload(User $user): array
    {
        $group = [];

        foreach ($this->groupFixtures() as $fixture) {
            [$home, $away] = $this->seedOrderScores()(
                $fixture->positions['home'],
                $fixture->positions['away'],
            );

            $group[] = ['fixture_id' => $fixture->id, 'home_goals' => $home, 'away_goals' => $away];
        }

        return [
            'pool_id' => $this->pool->id,
            'user_id' => $user->id,
            'group' => $group,
            'knockout' => [],
            'thirds_team_ids' => [],
        ];
    }

    /**
     * The tournament's group fixtures, each decorated with its two teams' seed positions.
     *
     * @return Collection<int, Fixture>
     */
    private function groupFixtures(): Collection
    {
        $positions = [];
        foreach ($this->tournament->groups()->with('teams')->get() as $group) {
            foreach ($group->teams as $team) {
                $positions[$team->id] = $team->pivot->position;
            }
        }

        return $this->tournament->groupFixtures()->with(['homeTeam', 'awayTeam'])->orderBy('match_number')->get()
            ->each(function ($fixture) use ($positions): void {
                $fixture->positions = [
                    'home' => $positions[$fixture->home_team_id],
                    'away' => $positions[$fixture->away_team_id],
                ];
            });
    }

    private function admin(): User
    {
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        return $admin;
    }
}
