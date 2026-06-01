<?php

namespace App\Http\Controllers;

use App\Enums\TournamentStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\Team;
use App\Models\Tournament;
use App\Services\Predictions\GroupStandings;
use App\Services\Predictions\TeamStanding;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class GameController extends Controller
{
    /**
     * List the available games (tournaments).
     */
    public function index(): Response
    {
        $games = Tournament::query()
            ->withCount(['groups', 'fixtures'])
            ->orderByDesc('starts_on')
            ->get()
            ->map(fn (Tournament $tournament): array => [
                ...$this->gameHeader($tournament),
                'groups_count' => $tournament->groups_count,
                'fixtures_count' => $tournament->fixtures_count,
            ]);

        return Inertia::render('games/index', ['games' => $games]);
    }

    /**
     * Show a single game's structure: groups, fixtures and the knockout bracket, plus the
     * viewer's pool standing for the dashboard banner.
     */
    public function show(Request $request, Tournament $tournament): Response
    {
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
        // an upfront-bracket tournament can show the match-up the player called.
        $entry = $tournament->entries()
            ->where('user_id', $request->user()->id)
            ->with([
                'groupPredictions',
                'knockoutPredictions.predictedHomeTeam',
                'knockoutPredictions.predictedAwayTeam',
            ])
            ->first();
        $groupPredictions = $entry?->groupPredictions->keyBy('fixture_id') ?? collect();
        $knockoutPredictions = $entry?->knockoutPredictions->keyBy('fixture_id') ?? collect();

        return Inertia::render('games/show', [
            'game' => [
                ...$this->gameHeader($tournament),
                'scoring_config' => $tournament->scoring_config,
                'allowed_transitions' => array_map(
                    fn (TournamentStatus $status): string => $status->value,
                    $tournament->status->allowedTransitions(),
                ),
                'can_review_scores' => (bool) $request->user()?->can('manage-tournament'),
            ],
            'groups' => $tournament->groups->map(
                fn (Group $group): array => $this->mapGroup($group, $groupPredictions),
            ),
            'bracket' => $this->mapBracket($tournament->knockoutFixtures, $knockoutPredictions, $tournament->predictsKnockoutBracket()),
            'pool' => $this->poolSummary($tournament, $request->user()->id),
        ]);
    }

    /**
     * The full pool table — every entry ranked by total points (unscored entries last).
     * Until results land, points are null and the page leads with an explainer state.
     */
    public function leaderboard(Request $request, Tournament $tournament): Response
    {
        $rows = $this->rankedEntries($tournament, $request->user()->id);

        return Inertia::render('games/leaderboard', [
            'game' => $this->gameHeader($tournament),
            'rows' => $rows->all(),
            'has_scores' => $rows->contains(fn (array $row): bool => $row['points'] !== null),
        ]);
    }

    /**
     * Rank a tournament's entries by total points, marking the current user's row.
     *
     * @return Collection<int, array{rank: int, name: string, initials: string, points: ?int, is_me: bool}>
     */
    private function rankedEntries(Tournament $tournament, int $userId): Collection
    {
        return $tournament->entries()
            ->with('user')
            ->orderBy('id')
            ->get()
            // Stable sort keeps the id order above for entries tied on points (nulls last).
            ->sortByDesc(fn (Entry $entry): int => $entry->total_points ?? PHP_INT_MIN)
            ->values()
            ->map(fn (Entry $entry, int $index): array => [
                'rank' => $index + 1,
                'name' => $entry->user_id === $userId ? 'You' : ($entry->user->name ?? 'Player'),
                'initials' => $this->initials($entry->user->name ?? ''),
                'points' => $entry->total_points,
                'is_me' => $entry->user_id === $userId,
                'movement' => $this->movement($entry),
            ]);
    }

    /**
     * Which way the entry moved on the leaderboard since the last approved batch: up, down,
     * same, or "new" (first appearance). Null until ranks have been snapshotted at least once.
     */
    private function movement(Entry $entry): ?string
    {
        if ($entry->rank === null) {
            return null;
        }

        if ($entry->previous_rank === null) {
            return 'new';
        }

        return match (true) {
            $entry->rank < $entry->previous_rank => 'up',
            $entry->rank > $entry->previous_rank => 'down',
            default => 'same',
        };
    }

    /**
     * A compact pool snapshot for the tournament dashboard: the viewer's standing plus the
     * top of the table.
     *
     * @return array{participants: int, has_scores: bool, me: ?array<string, mixed>, top: list<array<string, mixed>>}
     */
    private function poolSummary(Tournament $tournament, int $userId): array
    {
        $rows = $this->rankedEntries($tournament, $userId);

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
     * Shared header fields for a tournament across the games screens.
     *
     * @return array{slug: string, name: string, sport: string, status: string, starts_on: ?string, ends_on: ?string}
     */
    private function gameHeader(Tournament $tournament): array
    {
        return [
            'slug' => $tournament->slug,
            'name' => $tournament->name,
            'sport' => $tournament->sport->value,
            'status' => $tournament->status->value,
            'starts_on' => $tournament->starts_on?->toDateString(),
            'ends_on' => $tournament->ends_on?->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, GroupPrediction>  $predictions
     * @return array{name: string, teams: list<array<string, mixed>>, fixtures: list<array<string, mixed>>, standings: list<array<string, mixed>>}
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

        $standings = new GroupStandings($group, $results);
        $teamsById = $group->teams->keyBy('id');

        return collect($standings->ordered())
            ->values()
            ->map(fn (TeamStanding $standing, int $index): array => [
                'rank' => $index + 1,
                'team' => $this->teamRef($teamsById->get($standing->teamId)),
                'played' => $standing->played(),
                'won' => $standing->won,
                'drawn' => $standing->drawn,
                'lost' => $standing->lost,
                'goals_for' => $standing->goalsFor,
                'goals_against' => $standing->goalsAgainst,
                'goal_difference' => $standing->goalDifference(),
                'points' => $standing->points(),
                'form' => $standing->results,
            ])
            ->all();
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
