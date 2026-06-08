<?php

namespace App\Services\Scoring;

use App\Enums\FixtureStatus;
use App\Enums\LeaderboardCategory;
use App\Enums\LiveStatus;
use App\Enums\PhaseKey;
use App\Enums\ScoringStrategy;
use App\Http\Controllers\PoolController;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\GroupPrediction;
use App\Models\Pool;
use App\Models\Tournament;
use App\Services\Pools\PrizePot;
use App\Services\Predictions\GroupStandings;
use App\Services\Predictions\KnockoutSlotResolver;
use App\Services\Predictions\ManualTieOrdering;
use App\Services\Predictions\OfficialBracketProjector;
use Illuminate\Support\Facades\Cache;

/**
 * Computes a pool's projected leaderboard from the current live scores — "if every live match
 * ended right now, here is where you'd stand" — without writing anything. It overlays the live
 * scoreboard onto detached fixture clones, re-resolves the upfront bracket in-memory, scores every
 * entry through the pure {@see ScoreEngine::breakdownsByFixture()} pass, ranks them exactly like
 * {@see RankSnapshotter}, and derives rank movement against the current official standings plus a
 * projected payout. Winner-dependent knockout bonuses are held while a match is undecided (the
 * live clone carries no winner) and surfaced separately as a pending amount.
 */
class LiveProjection
{
    public function __construct(
        private readonly ScoreEngine $engine = new ScoreEngine,
        private readonly ScoringRulesFactory $rulesFactory = new ScoringRulesFactory,
        private readonly KnockoutSlotResolver $slotResolver = new KnockoutSlotResolver,
    ) {}

    /**
     * The projection for a pool, computed once per live-state change and shared by every viewer.
     */
    public function cachedFor(Pool $pool): LiveProjectionResult
    {
        $version = $this->version($pool->tournament);
        $key = "live-projection:2:pool:{$pool->id}:v:{$version}";

        // Cache plain data, never the DTO itself: a serialising store (redis/file) would round-trip
        // a cached object into __PHP_Incomplete_Class. The boards are nested scalars and the version
        // is a string, so this payload serialises cleanly; rebuild the DTO from it on the way out.
        $payload = Cache::remember($key, now()->addMinutes(10), fn (): array => [
            'boards' => $this->project($pool, $version)->boards,
            'version' => $version,
        ]);

        return new LiveProjectionResult($payload['boards'], $payload['version']);
    }

    public function project(Pool $pool, ?string $version = null): LiveProjectionResult
    {
        $tournament = $pool->tournament;
        $tournament->loadMissing(['groups.teams']);
        $tournament->load([
            'groups.fixtures.liveState',
            'knockoutFixtures.phase',
            'knockoutFixtures.liveState',
        ]);
        $pool->load([
            'entries.user',
            'entries.groupPredictions',
            'entries.knockoutPredictions',
            'entries.standings',
        ]);

        $config = ScoringConfig::fromPool($pool);
        $rules = $this->rulesFactory->make($pool->scoring_strategy);

        $fixturesById = $this->overlayFixtures($tournament);

        // Upfront pools self-derive their bracket, so live group results must cascade into the
        // knockout participants before scoring. Phased pools predict the official bracket directly.
        if ($pool->scoring_strategy === ScoringStrategy::UpfrontBracket) {
            $this->reresolveBracket($tournament, $fixturesById);
        }

        $metricsByEntry = [];
        foreach ($pool->entries as $entry) {
            $breakdowns = $this->engine->breakdownsByFixture($entry, $fixturesById, $rules, $config);
            $metricsByEntry[$entry->id] = LeaderboardMetrics::fromBreakdowns($breakdowns);
        }

        $pendingByEntry = $this->pendingBonuses($pool, $fixturesById, $config);
        $prizesByPlace = $this->prizesByPlace($pool);

        $boards = [];
        foreach (LeaderboardCategory::ordered() as $category) {
            $boards[$category->value] = $this->board($category, $pool, $metricsByEntry, $pendingByEntry, $prizesByPlace);
        }

        return new LiveProjectionResult($boards, $version ?? $this->version($tournament));
    }

    /**
     * Clone every fixture and overlay the live scoreline onto the clones for matches that are live,
     * leaving the persisted models untouched. A live knockout clone carries a null winner so the
     * champion/advancing bonuses are held until the result is official.
     *
     * @return array<int, Fixture>
     */
    private function overlayFixtures(Tournament $tournament): array
    {
        $fixturesById = [];

        foreach ($tournament->groups as $group) {
            foreach ($group->fixtures as $fixture) {
                $fixturesById[$fixture->id] = $this->overlay($fixture);
            }
        }

        foreach ($tournament->knockoutFixtures as $fixture) {
            $fixturesById[$fixture->id] = $this->overlay($fixture);
        }

        return $fixturesById;
    }

    private function overlay(Fixture $fixture): Fixture
    {
        $clone = clone $fixture;
        $live = $fixture->liveState;

        if ($fixture->status === FixtureStatus::Live
            && $live !== null
            && in_array($live->status, [LiveStatus::Live, LiveStatus::Ended], true)
            && $live->home_goals !== null
            && $live->away_goals !== null) {
            $clone->home_goals = $live->home_goals;
            $clone->away_goals = $live->away_goals;
            $clone->winner_team_id = null;
        }

        return $clone;
    }

    /**
     * Re-resolve the official knockout participants in-memory from the overlaid (live + banked)
     * group scores, mirroring {@see OfficialBracketProjector} but writing
     * only onto the clones. Undecided live feeders advance no one, so downstream slots stay empty.
     *
     * @param  array<int, Fixture>  $fixturesById
     */
    private function reresolveBracket(Tournament $tournament, array $fixturesById): void
    {
        $ordering = ManualTieOrdering::fromTournament($tournament);

        $standings = [];
        foreach ($tournament->groups as $group) {
            $results = [];
            foreach ($group->fixtures as $fixture) {
                $clone = $fixturesById[$fixture->id];

                if ($clone->home_goals !== null && $clone->away_goals !== null) {
                    $results[$fixture->id] = new GroupPrediction([
                        'home_goals' => $clone->home_goals,
                        'away_goals' => $clone->away_goals,
                    ]);
                }
            }

            $standings[$group->name] = new GroupStandings($group, $results, $ordering->forGroup($group->name));
        }

        $resolved = $this->slotResolver->resolve(
            $standings,
            $tournament->knockoutFixtures,
            fn (int $feederId): ?int => $fixturesById[$feederId]->winner_team_id ?? null,
            $tournament->groups,
            $ordering->thirds,
        )['resolved'];

        foreach ($tournament->knockoutFixtures as $fixture) {
            $slot = $resolved[$fixture->id] ?? ['home' => null, 'away' => null];
            $clone = $fixturesById[$fixture->id];
            $clone->home_team_id = $slot['home'];
            $clone->away_team_id = $slot['away'];
        }
    }

    /**
     * The winner-dependent bonus each entry would gain if every live knockout's current leader held
     * — display only, never folded into projected points. Upfront pools only hold the champion
     * bonus (the final); phased pools hold an advancing bonus on every live knockout round.
     *
     * @param  array<int, Fixture>  $fixturesById
     * @return array<int, int>
     */
    private function pendingBonuses(Pool $pool, array $fixturesById, ScoringConfig $config): array
    {
        $isUpfront = $pool->scoring_strategy === ScoringStrategy::UpfrontBracket;
        $pending = [];

        foreach ($pool->entries as $entry) {
            $total = 0;

            foreach ($entry->knockoutPredictions as $prediction) {
                $fixture = $fixturesById[$prediction->fixture_id] ?? null;
                $leader = $fixture === null ? null : $this->liveLeader($fixture);

                if ($leader === null
                    || $prediction->advancing_team_id === null
                    || (int) $prediction->advancing_team_id !== $leader) {
                    continue;
                }

                if ($isUpfront) {
                    if ($fixture->phase->key === PhaseKey::Final) {
                        $total += $config->champion();
                    }
                } else {
                    $total += $config->knockoutAdvancingBonus();
                }
            }

            $pending[$entry->id] = $total;
        }

        return $pending;
    }

    /**
     * The team currently leading a held live knockout (winner not yet decided), or null when it is
     * drawn, unscored, or already official.
     */
    private function liveLeader(Fixture $clone): ?int
    {
        if ($clone->winner_team_id !== null
            || $clone->home_goals === null
            || $clone->away_goals === null
            || $clone->home_goals === $clone->away_goals) {
            return null;
        }

        return $clone->home_goals > $clone->away_goals ? $clone->home_team_id : $clone->away_team_id;
    }

    /**
     * The projected payout per finishing place for a paid pool (place => amount), or an empty map
     * for a free pool. The pot is fixed; projection only re-orders who holds each paid place.
     *
     * @return array<int, float>
     */
    private function prizesByPlace(Pool $pool): array
    {
        if ((float) $pool->entry_price <= 0) {
            return [];
        }

        $byPlace = [];
        foreach (PrizePot::forPool($pool, $pool->entries->count())->prizes as $prize) {
            $byPlace[$prize['place']] = $prize['amount'];
        }

        return $byPlace;
    }

    /**
     * One board's ranked projected rows. Mirrors {@see PoolController::board}'s
     * shape (value desc, tiebreaker desc, entry id asc) plus the projected payout, movement vs the
     * current official rank, and the pending winner bonus.
     *
     * @param  array<int, LeaderboardMetrics>  $metricsByEntry
     * @param  array<int, int>  $pendingByEntry
     * @param  array<int, float>  $prizesByPlace
     * @return list<array<string, mixed>>
     */
    private function board(LeaderboardCategory $category, Pool $pool, array $metricsByEntry, array $pendingByEntry, array $prizesByPlace): array
    {
        $ordered = $pool->entries
            ->sort(function (Entry $a, Entry $b) use ($category, $metricsByEntry): int {
                $metricsA = $metricsByEntry[$a->id];
                $metricsB = $metricsByEntry[$b->id];

                return ($category->valueFor($metricsB) <=> $category->valueFor($metricsA))
                    ?: ($category->tiebreakerFor($metricsB) <=> $category->tiebreakerFor($metricsA))
                    ?: ($a->id <=> $b->id);
            })
            ->values();

        return $ordered
            ->map(function (Entry $entry, int $index) use ($category, $metricsByEntry, $pendingByEntry, $prizesByPlace): array {
                $rank = $index + 1;
                $metrics = $metricsByEntry[$entry->id];
                $officialRank = $this->officialRank($entry, $category);

                return [
                    'entry_id' => $entry->id,
                    'user_id' => $entry->user_id,
                    'name' => $entry->user->name ?? 'Player',
                    'initials' => $this->initials($entry->user->name ?? ''),
                    'avatar' => $entry->user->avatar,
                    'rank' => $rank,
                    'primary_value' => $category->valueFor($metrics),
                    'secondary_value' => $category === LeaderboardCategory::Overall ? null : $category->tiebreakerFor($metrics),
                    'live_gain' => $category->valueFor($metrics) - $this->officialValue($entry, $category),
                    'official_rank' => $officialRank,
                    'movement' => RankMovement::direction($rank, $officialRank),
                    'movement_delta' => RankMovement::delta($rank, $officialRank),
                    'projected_prize' => $category->awardsPrizes() ? ($prizesByPlace[$rank] ?? null) : null,
                    'pending_bonus' => $category === LeaderboardCategory::Overall ? ($pendingByEntry[$entry->id] ?? 0) : 0,
                ];
            })
            ->all();
    }

    private function officialRank(Entry $entry, LeaderboardCategory $category): ?int
    {
        if ($category === LeaderboardCategory::Overall) {
            return $entry->rank;
        }

        return $entry->standings->firstWhere('category', $category)?->rank;
    }

    /**
     * The entry's banked official value for this board — the baseline the projected value is gained
     * over. Mirrors {@see officialRank()}; an unscored entry (no points/standing yet) reads as 0.
     */
    private function officialValue(Entry $entry, LeaderboardCategory $category): int
    {
        if ($category === LeaderboardCategory::Overall) {
            return $entry->total_points ?? 0;
        }

        return $entry->standings->firstWhere('category', $category)?->value ?? 0;
    }

    private function initials(string $name): string
    {
        $parts = array_values(array_filter(preg_split('/\s+/', trim($name)) ?: []));
        $letters = array_map(fn (string $part): string => mb_substr($part, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters));
    }

    private function version(Tournament $tournament): string
    {
        $max = FixtureLiveState::query()
            ->whereRelation('fixture', 'tournament_id', $tournament->id)
            ->max('updated_at');

        return $max === null ? 'none' : (string) $max;
    }
}
