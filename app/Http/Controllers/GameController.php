<?php

namespace App\Http\Controllers;

use App\Enums\LeaderboardCategory;
use App\Http\Controllers\Concerns\BuildsGameIdentity;
use App\Http\Requests\Games\JoinGameRequest;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\LeaderboardStanding;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Games\PrizePool;
use App\Services\Predictions\GroupStandings;
use App\Services\Predictions\GroupStandingsPresenter;
use App\Services\Predictions\PlayerComparison;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    use BuildsGameIdentity;

    /**
     * List the available games (tournaments).
     */
    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $games = Game::query()
            // The size of each game's pool — distinct per game (sibling games each have their own
            // entries), so it helps a player choose which to enter.
            ->withCount('entries')
            // Whether the viewer has already joined each pool, so the card can flag it and never
            // read "join" as the navigate-to-game action.
            ->withExists(['entries as joined' => fn ($query) => $query->where('user_id', $userId)])
            ->with(['tournament' => fn ($query) => $query
                ->withCount(['groups', 'fixtures'])
                // The tournament's sibling game ids, so each list item can carry its position among
                // them — used by the frontend to give same-tournament games distinct kit colors.
                ->with('games:id,tournament_id'),
            ])
            // Newest competition first; games over the same tournament keep a stable order by id.
            ->orderByDesc(
                Tournament::select('starts_on')->whereColumn('tournaments.id', 'games.tournament_id'),
            )
            ->orderBy('id')
            ->paginate(12)
            ->through(function (Game $game): array {
                $siblingIds = $game->tournament->games->sortBy('id')->pluck('id')->values();

                return [
                    // gameHeader() already carries scoring_label (via the game-identity payload).
                    ...$this->gameHeader($game),
                    // The one-line explainer of how this game scores — what sets it apart from its
                    // siblings over the same tournament.
                    'scoring_description' => $game->scoring_strategy->description(),
                    'groups_count' => $game->tournament->groups_count,
                    'fixtures_count' => $game->tournament->fixtures_count,
                    'tournament' => [
                        'id' => $game->tournament->id,
                        'name' => $game->tournament->name,
                    ],
                    // 0-based position among the tournament's games (by id), stable across pages, so
                    // a game's accent colour never shifts when the list paginates.
                    'accent_index' => $siblingIds->search($game->id),
                    'players_count' => $game->entries_count,
                    // Whether the viewer is already in this pool.
                    'joined' => (bool) $game->joined,
                    // Whether joining is still open — the card shows the buy-in and percentage prize
                    // split while joinable, then switches to the now-final raw prize amounts.
                    'can_join' => $game->acceptsPredictions(),
                    // The buy-in and the prizes computed from the current pool size, so a player can
                    // weigh the stakes before opening the game.
                    'pricing' => PrizePool::forGame($game, $game->entries_count)->toArray(),
                ];
            });

        return Inertia::render('games/index', ['games' => $games]);
    }

    /**
     * Show a single game's structure: groups, fixtures and the knockout bracket, plus the
     * viewer's pool standing for the dashboard banner.
     */
    public function show(Request $request, Game $game): Response
    {
        $tournament = $game->tournament;

        $tournament->load([
            'groups.teams',
            'groups.fixtures' => fn ($query) => $query->orderBy('match_number'),
            'groups.fixtures.homeTeam',
            'groups.fixtures.awayTeam',
            'knockoutFixtures' => fn ($query) => $query->orderBy('match_number'),
            'knockoutFixtures.phase',
            'knockoutFixtures.homeTeam',
            'knockoutFixtures.awayTeam',
            'knockoutFixtures.winner',
        ]);

        // The viewer's own picks, so each fixture can show its predicted scoreline alongside the
        // official result and the points it earned. Knockout picks carry their predicted teams so
        // an upfront-bracket game can show the match-up the player called.
        $entry = $game->entries()
            ->where('user_id', $request->user()->id)
            ->with([
                'groupPredictions',
                'knockoutPredictions.predictedHomeTeam',
                'knockoutPredictions.predictedAwayTeam',
            ])
            ->first();
        $groupPredictions = $entry?->groupPredictions->keyBy('fixture_id') ?? collect();
        $knockoutPredictions = $entry?->knockoutPredictions->keyBy('fixture_id') ?? collect();

        // The "compare players" feature: when the page carries a ?compare= list of other entries,
        // resolve their results into a comparison payload alongside the viewer's. `players` is the
        // always-present directory the picker filters; `comparison` is null in normal mode.
        $compareIds = $this->parseCompareIds($request, $game, $request->user()->id);

        // Built once so the pricing's player count and the banner's pool snapshot stay consistent.
        $pool = $this->poolSummary($game, $request->user()->id);

        return Inertia::render('games/show', [
            'game' => [
                // gameHeader() already carries scoring_label (via the game-identity payload).
                ...$this->gameHeader($game),
                'scoring_strategy' => $game->scoring_strategy->value,
                'scoring_description' => $game->scoring_strategy->description(),
                'how_to_play' => $game->scoring_strategy->howToPlay(),
                'scoring_config' => $game->scoring_config,
                'predictions_lock_at' => $game->predictionsLockAt()?->toIso8601String(),
                'can_review_scores' => (bool) $request->user()?->can('manage-tournament'),
                // Whether the player may still join (and pay in): the join window closes with the
                // group-stage prediction lock, mirroring can_edit on the predict screen.
                'can_join' => $game->acceptsPredictions(),
                'pricing' => PrizePool::forGame($game, $pool['participants'])->toArray(),
                'leaderboards' => $this->boardDescriptors(),
            ],
            'groups' => $tournament->groups->map(
                fn (Group $group): array => $this->mapGroup($group, $groupPredictions),
            ),
            'bracket' => $this->mapBracket($tournament->knockoutFixtures, $knockoutPredictions, $game->predictsKnockoutBracket()),
            'pool' => $pool,
            'boardSummaries' => $this->boardSummaries($game, $request->user()->id),
            'players' => $this->playerDirectory($game, $request->user()->id),
            'comparison' => (new PlayerComparison)->build($game, $request->user()->id, $compareIds),
        ]);
    }

    /**
     * Join the game's pool. Creates the player's entry (the act of joining), which is the
     * prerequisite for making predictions. Payment is arranged externally with the organizer, so
     * this only records participation. Idempotent — backed by the unique (game_id, user_id) index.
     */
    public function join(JoinGameRequest $request, Game $game): RedirectResponse
    {
        $game->entries()->firstOrCreate(['user_id' => $request->user()->id]);

        return to_route('games.show', $game);
    }

    /**
     * The opponent entry ids to compare, parsed from the `?compare=` CSV. Sanitised to at most
     * three distinct other entries that belong to *this* game (never the viewer's own), in the
     * order requested; foreign, duplicate or garbage ids are silently dropped.
     *
     * @return list<int>
     */
    private function parseCompareIds(Request $request, Game $game, int $viewerUserId): array
    {
        $raw = $request->query('compare');

        if (! is_string($raw) || trim($raw) === '') {
            return [];
        }

        $ids = collect(explode(',', $raw))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($ids->isEmpty()) {
            return [];
        }

        $valid = $game->entries()
            ->whereIn('id', $ids->all())
            ->where('user_id', '!=', $viewerUserId)
            ->pluck('id')
            ->all();

        return $ids
            ->filter(fn (int $id): bool => in_array($id, $valid, true))
            ->take(3)
            ->values()
            ->all();
    }

    /**
     * Every entry in the game as a lightweight pick-list row (no heavy prediction data), ranked by
     * points so the "Add player" picker can show and filter the whole pool. The viewer is flagged
     * so the picker can exclude their own row.
     *
     * @return list<array{entry_id: int, user_id: int, name: string, initials: string, avatar: ?string, points: ?int, rank: int, is_me: bool}>
     */
    private function playerDirectory(Game $game, int $userId): array
    {
        return $game->entries()
            ->with('user:id,name,avatar_path')
            ->orderBy('id')
            ->get()
            ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
            ->values()
            ->map(fn (Entry $entry, int $index): array => [
                'entry_id' => $entry->id,
                'user_id' => $entry->user_id,
                'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
                'initials' => $this->initials($entry->user->name ?? ''),
                'avatar' => $entry->user->avatar,
                'points' => $entry->total_points,
                'rank' => $index + 1,
                'is_me' => $entry->user_id === $userId,
            ])
            ->all();
    }

    /**
     * The Leaderboards page — every board's full table. The viewer's row is marked, and each board
     * reports whether scoring has begun so the page can lead with an explainer state. `active_board`
     * preselects a tab when a valid `?board=` is given (e.g. from a leaders-strip link).
     */
    public function leaderboard(Request $request, Game $game): Response
    {
        $boards = $this->boards($game, $request->user()->id);
        $requested = $request->query('board');
        $active = is_string($requested) && in_array($requested, array_column($boards, 'key'), true)
            ? $requested
            : null;

        return Inertia::render('games/leaderboard', [
            'game' => $this->gameHeader($game),
            'boards' => $boards,
            'active_board' => $active,
        ]);
    }

    /**
     * Every leaderboard for a game, in display order, each with its full ranked table.
     *
     * @return list<array<string, mixed>>
     */
    private function boards(Game $game, int $userId): array
    {
        $entries = $game->entries()
            ->with(['user', 'standings'])
            ->orderBy('id')
            ->get();

        $hasScores = $entries->contains(fn (Entry $entry): bool => $entry->total_points !== null);

        return array_map(
            fn (LeaderboardCategory $category): array => $this->board($category, $entries, $userId, $hasScores),
            LeaderboardCategory::ordered(),
        );
    }

    /**
     * One board's payload: its labels plus the entries ranked for that category. The Overall board
     * ranks by `total_points` straight off the entry (mirroring the snapshot); every other board
     * ranks its {@see LeaderboardStanding} rows by value, then tie-break, then entry id.
     *
     * @param  Collection<int, Entry>  $entries
     * @return array<string, mixed>
     */
    private function board(LeaderboardCategory $category, Collection $entries, int $userId, bool $hasScores): array
    {
        if ($category === LeaderboardCategory::Overall) {
            $rows = $entries
                ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
                ->values()
                ->map(fn (Entry $entry, int $index): array => [
                    'rank' => $index + 1,
                    'entry_id' => $entry->id,
                    'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
                    'initials' => $this->initials($entry->user->name ?? ''),
                    'avatar' => $entry->user->avatar,
                    'primary_value' => $entry->total_points,
                    'secondary_value' => null,
                    'is_me' => $entry->user_id === $userId,
                    'movement' => $this->movement($entry->rank, $entry->previous_rank),
                ])
                ->all();
        } else {
            $rows = $entries
                ->map(fn (Entry $entry): array => [
                    'entry' => $entry,
                    'standing' => $entry->standings->firstWhere('category', $category),
                ])
                ->sort($this->compareStandings(...))
                ->values()
                ->map(fn (array $pair, int $index): array => [
                    'rank' => $index + 1,
                    'entry_id' => $pair['entry']->id,
                    'name' => $pair['entry']->user_id === $userId ? 'You' : ($pair['entry']->user->name ?? 'Player'),
                    'initials' => $this->initials($pair['entry']->user->name ?? ''),
                    'avatar' => $pair['entry']->user->avatar,
                    'primary_value' => $pair['standing']?->value ?? 0,
                    'secondary_value' => $pair['standing']?->tiebreaker ?? 0,
                    'is_me' => $pair['entry']->user_id === $userId,
                    'movement' => $this->movement($pair['standing']?->rank, $pair['standing']?->previous_rank),
                ])
                ->all();
        }

        return [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'primary_stat_label' => $category->primaryStatLabel(),
            'secondary_stat_label' => $category->secondaryStatLabel(),
            'has_scores' => $hasScores,
            'rows' => $rows,
        ];
    }

    /**
     * Order two entry/standing pairs for a board: value descending, then tie-break descending, then
     * entry id ascending (stable). Missing standings sort as zero.
     *
     * @param  array{entry: Entry, standing: ?LeaderboardStanding}  $a
     * @param  array{entry: Entry, standing: ?LeaderboardStanding}  $b
     */
    private function compareStandings(array $a, array $b): int
    {
        return ($b['standing']?->value ?? 0) <=> ($a['standing']?->value ?? 0)
            ?: ($b['standing']?->tiebreaker ?? 0) <=> ($a['standing']?->tiebreaker ?? 0)
            ?: $a['entry']->id <=> $b['entry']->id;
    }

    /**
     * A summary of each non-Overall board for the game page: who leads it, and where the viewer
     * stands on it. Both are null until scoring has begun (or the viewer has no entry).
     *
     * @return list<array<string, mixed>>
     */
    private function boardSummaries(Game $game, int $userId): array
    {
        return collect($this->boards($game, $userId))
            ->reject(fn (array $board): bool => $board['key'] === LeaderboardCategory::Overall->value)
            ->map(function (array $board): array {
                $mine = $board['has_scores']
                    ? collect($board['rows'])->firstWhere('is_me', true)
                    : null;

                return [
                    'key' => $board['key'],
                    'label' => $board['label'],
                    'primary_stat_label' => $board['primary_stat_label'],
                    // The leader carries its entry id + is_me so compare selection can add this
                    // board's winner straight from the card.
                    'leader' => $board['has_scores'] && $board['rows'] !== []
                        ? [
                            'entry_id' => $board['rows'][0]['entry_id'],
                            'name' => $board['rows'][0]['name'],
                            'initials' => $board['rows'][0]['initials'],
                            'avatar' => $board['rows'][0]['avatar'],
                            'primary_value' => $board['rows'][0]['primary_value'],
                            'is_me' => $board['rows'][0]['is_me'],
                        ]
                        : null,
                    'you' => $mine === null ? null : [
                        'rank' => $mine['rank'],
                        'primary_value' => $mine['primary_value'],
                        'movement' => $mine['movement'],
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * The board descriptors (no rows) for the "How this game works" dialog.
     *
     * @return list<array<string, mixed>>
     */
    private function boardDescriptors(): array
    {
        return array_map(fn (LeaderboardCategory $category): array => [
            'key' => $category->value,
            'label' => $category->label(),
            'description' => $category->description(),
            'primary_stat_label' => $category->primaryStatLabel(),
            'secondary_stat_label' => $category->secondaryStatLabel(),
        ], LeaderboardCategory::ordered());
    }

    /**
     * Rank a game's entries by total points, marking the current user's row.
     *
     * @return Collection<int, array{rank: int, entry_id: int, name: string, initials: string, avatar: ?string, points: ?int, is_me: bool, movement: ?string}>
     */
    private function rankedEntries(Game $game, int $userId): Collection
    {
        return $game->entries()
            ->with('user')
            ->orderBy('id')
            ->get()
            // Stable sort keeps the id order above for entries tied on points (nulls last).
            ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
            ->values()
            ->map(fn (Entry $entry, int $index): array => [
                'rank' => $index + 1,
                'entry_id' => $entry->id,
                'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
                'initials' => $this->initials($entry->user->name ?? ''),
                'avatar' => $entry->user->avatar,
                'points' => $entry->total_points,
                'is_me' => $entry->user_id === $userId,
                'movement' => $this->movement($entry->rank, $entry->previous_rank),
            ]);
    }

    /**
     * Which way a rank moved on a leaderboard since the last approved batch: up, down, same, or
     * "new" (first appearance). Null until ranks have been snapshotted at least once.
     */
    private function movement(?int $rank, ?int $previousRank): ?string
    {
        if ($rank === null) {
            return null;
        }

        if ($previousRank === null) {
            return 'new';
        }

        return match (true) {
            $rank < $previousRank => 'up',
            $rank > $previousRank => 'down',
            default => 'same',
        };
    }

    /**
     * A compact pool snapshot for the game dashboard: the viewer's standing plus the
     * top of the table.
     *
     * @return array{participants: int, has_scores: bool, me: ?array<string, mixed>, top: list<array<string, mixed>>}
     */
    private function poolSummary(Game $game, int $userId): array
    {
        $rows = $this->rankedEntries($game, $userId);

        return [
            'participants' => $rows->count(),
            'has_scores' => $rows->contains(fn (array $row): bool => $row['points'] !== null),
            'me' => $rows->firstWhere('is_me', true),
            'top' => $rows->take(4)->values()->all(),
        ];
    }

    /**
     * Up to two initials from a display name (e.g. "Marina Jones" -> "MJ").
     */
    private function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));

        $letters = collect($parts)
            ->take(2)
            ->map(fn (string $part): string => mb_substr($part, 0, 1))
            ->implode('');

        return mb_strtoupper($letters) ?: '?';
    }

    /**
     * Shared header fields for a game across the games screens. The game carries its own
     * identity (slug/name/source/accent/scoring style {@see BuildsGameIdentity}) while the
     * lifecycle, sport and dates come from the shared competition it is played over.
     *
     * @return array{slug: string, name: string, source: string, accent: ?string, scoring_label: string, sport: string, status: string, starts_on: ?string, ends_on: ?string}
     */
    private function gameHeader(Game $game): array
    {
        $tournament = $game->tournament;

        return [
            ...$this->gameIdentity($game),
            'sport' => $tournament->sport->value,
            'status' => $tournament->status->value,
            'starts_on' => $tournament->starts_on?->toDateString(),
            'ends_on' => $tournament->ends_on?->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, GroupPrediction>  $predictions
     * @return array{name: string, teams: list<array<string, mixed>>, fixtures: list<array<string, mixed>>, standings: list<array<string, mixed>>, predicted_standings: list<array<string, mixed>>|null}
     */
    private function mapGroup(Group $group, Collection $predictions): array
    {
        return [
            'name' => $group->name,
            'teams' => $group->teams
                ->map(fn (Team $team): array => [
                    ...$this->teamRef($team),
                    'position' => $team->pivot->position,
                ])
                ->all(),
            'fixtures' => $group->fixtures->map(function (Fixture $fixture) use ($predictions): array {
                $prediction = $predictions->get($fixture->id);

                return [
                    'fixture_id' => $fixture->id,
                    'match_number' => $fixture->match_number,
                    'home' => $this->teamRef($fixture->homeTeam),
                    'away' => $this->teamRef($fixture->awayTeam),
                    'home_goals' => $fixture->home_goals,
                    'away_goals' => $fixture->away_goals,
                    'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                    'venue' => $fixture->venue,
                    'venue_timezone' => $fixture->venue_timezone,
                    'prediction' => $prediction !== null && $prediction->home_goals !== null && $prediction->away_goals !== null
                        ? [
                            'home_goals' => $prediction->home_goals,
                            'away_goals' => $prediction->away_goals,
                            'points_awarded' => $prediction->points_awarded,
                        ]
                        : null,
                ];
            })->all(),
            'standings' => $this->officialStandings($group),
            'predicted_standings' => $this->predictedStandings($group, $predictions),
        ];
    }

    /**
     * The official live group table — the same FIFA-correct engine used for predicted
     * standings, fed the real (already-played) fixture scores instead of a user's picks.
     * Unplayed fixtures are skipped, so the table is seed-ordered before kick-off and
     * fills in as results land.
     *
     * @return list<array<string, mixed>>
     */
    private function officialStandings(Group $group): array
    {
        $results = $group->fixtures
            ->filter(fn (Fixture $fixture): bool => $fixture->home_goals !== null && $fixture->away_goals !== null)
            ->mapWithKeys(fn (Fixture $fixture): array => [$fixture->id => new GroupPrediction([
                'home_goals' => $fixture->home_goals,
                'away_goals' => $fixture->away_goals,
            ])])
            ->all();

        return GroupStandingsPresenter::rows(
            new GroupStandings($group, $results),
            $group->teams->keyBy('id'),
        );
    }

    /**
     * The viewer's projected group table from their own predicted scores — the same engine as
     * the official table, so the two can be compared row for row. Returns null when the viewer
     * has predicted none of this group's fixtures, so the page can show a "no prediction" state
     * rather than an all-zero table.
     *
     * @param  Collection<int, GroupPrediction>  $groupPredictions  the viewer's picks keyed by fixture id
     * @return list<array<string, mixed>>|null
     */
    private function predictedStandings(Group $group, Collection $groupPredictions): ?array
    {
        $predictions = $group->fixtures
            ->filter(fn (Fixture $fixture): bool => $groupPredictions->has($fixture->id))
            ->mapWithKeys(fn (Fixture $fixture): array => [$fixture->id => $groupPredictions->get($fixture->id)])
            ->all();

        if ($predictions === []) {
            return null;
        }

        return GroupStandingsPresenter::rows(
            new GroupStandings($group, $predictions),
            $group->teams->keyBy('id'),
        );
    }

    /**
     * Group the knockout fixtures into bracket columns, ordered by phase progression. Each
     * fixture carries the official result (teams, score, penalties, advancing team) plus the
     * viewer's own prediction and the points it earned, so a settled card can show all three.
     *
     * @param  Collection<int, Fixture>  $fixtures
     * @param  Collection<int, KnockoutPrediction>  $predictions  keyed by fixture id
     * @param  bool  $showPredictedTeams  whether to expose the viewer's predicted teams (upfront brackets only)
     * @return list<array<string, mixed>>
     */
    private function mapBracket($fixtures, Collection $predictions, bool $showPredictedTeams): array
    {
        return $fixtures
            ->groupBy(fn (Fixture $fixture): string => $fixture->phase->key->value)
            ->map(fn ($phaseFixtures): array => [
                'phase_key' => $phaseFixtures->first()->phase->key->value,
                'phase_name' => $phaseFixtures->first()->phase->name,
                'sort_order' => $phaseFixtures->first()->phase->sort_order,
                'fixtures' => $phaseFixtures->map(function (Fixture $fixture) use ($predictions, $showPredictedTeams): array {
                    $prediction = $predictions->get($fixture->id);

                    return [
                        'fixture_id' => $fixture->id,
                        'match_number' => $fixture->match_number,
                        'bracket_slot' => $fixture->bracket_slot,
                        'home' => $this->teamRef($fixture->homeTeam),
                        'away' => $this->teamRef($fixture->awayTeam),
                        'home_label' => $fixture->homeTeam?->name ?? $fixture->home_placeholder_label,
                        'away_label' => $fixture->awayTeam?->name ?? $fixture->away_placeholder_label,
                        'home_goals' => $fixture->home_goals,
                        'away_goals' => $fixture->away_goals,
                        'home_penalties' => $fixture->home_penalties,
                        'away_penalties' => $fixture->away_penalties,
                        'winner_team_id' => $fixture->winner_team_id,
                        'kicks_off_at' => $fixture->kicks_off_at?->toIso8601String(),
                        'venue' => $fixture->venue,
                        'venue_timezone' => $fixture->venue_timezone,
                        'prediction' => $prediction === null ? null : [
                            'home_goals' => $prediction->home_goals,
                            'away_goals' => $prediction->away_goals,
                            'advancing_team_id' => $prediction->advancing_team_id,
                            'points_awarded' => $prediction->points_awarded,
                            'predicted_home' => $showPredictedTeams ? $this->teamRef($prediction->predictedHomeTeam) : null,
                            'predicted_away' => $showPredictedTeams ? $this->teamRef($prediction->predictedAwayTeam) : null,
                        ],
                    ];
                })->all(),
            ])
            ->sortBy('sort_order')
            ->values()
            ->all();
    }

    /**
     * @return array{id: int, name: string, code: ?string, is_placeholder: bool, flag_url: string}|null
     */
    private function teamRef(?Team $team): ?array
    {
        if ($team === null) {
            return null;
        }

        return [
            'id' => $team->id,
            'name' => $team->name,
            'code' => $team->code,
            'is_placeholder' => $team->is_placeholder,
            'flag_url' => $team->flag_url,
        ];
    }
}
