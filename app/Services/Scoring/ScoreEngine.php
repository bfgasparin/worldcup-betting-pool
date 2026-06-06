<?php

namespace App\Services\Scoring;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\LeaderboardStanding;
use App\Models\Pool;
use App\Services\Scoring\Strategies\ScoringRules;
use Illuminate\Support\Facades\DB;

/**
 * Recomputes points for an entire pool from the official results currently on its tournament's
 * fixtures. It resolves the pool's scoring strategy, scores every prediction (writing
 * `points_awarded` per group/knockout prediction) and rolls them up into `Entry.total_points`.
 *
 * The same pass aggregates each entry's per-category metrics ({@see LeaderboardMetrics}) and upserts
 * one {@see LeaderboardStanding} per {@see LeaderboardCategory}, so every leaderboard is
 * recomputed together.
 *
 * It fully overwrites prior values rather than incrementing, so it is safe to run after every
 * approved batch and after corrections — a prediction whose fixture has no official result yet
 * is reset to null (unscored), and `total_points` stays null until the entry has at least one
 * scored prediction (so the leaderboard keeps its "points unlock as results land" state).
 */
class ScoreEngine
{
    public function __construct(private readonly ScoringRulesFactory $rulesFactory = new ScoringRulesFactory) {}

    public function recompute(Pool $pool): void
    {
        $config = ScoringConfig::fromPool($pool);
        $rules = $this->rulesFactory->make($pool->scoring_strategy);

        // Structure and official results live on the shared tournament; entries on the pool.
        $tournament = $pool->tournament;
        $tournament->load([
            'groups.fixtures',
            'knockoutFixtures.phase',
        ]);
        $pool->load([
            'entries.groupPredictions',
            'entries.knockoutPredictions',
        ]);

        $fixturesById = [];
        foreach ($tournament->groups as $group) {
            foreach ($group->fixtures as $fixture) {
                $fixturesById[$fixture->id] = $fixture;
            }
        }
        foreach ($tournament->knockoutFixtures as $fixture) {
            $fixturesById[$fixture->id] = $fixture;
        }

        DB::transaction(function () use ($pool, $fixturesById, $rules, $config): void {
            foreach ($pool->entries as $entry) {
                $this->scoreEntry($entry, $fixturesById, $rules, $config);
            }
        });
    }

    /**
     * @param  array<int, Fixture>  $fixturesById
     */
    private function scoreEntry(Entry $entry, array $fixturesById, ScoringRules $rules, ScoringConfig $config): void
    {
        $breakdowns = $this->breakdownsByFixture($entry, $fixturesById, $rules, $config);

        // Write each scored prediction's points; reset everything else to null (unscored).
        foreach ($entry->groupPredictions as $prediction) {
            $prediction->update(['points_awarded' => $breakdowns[$prediction->fixture_id]->points ?? null]);
        }

        foreach ($entry->knockoutPredictions as $prediction) {
            $prediction->update(['points_awarded' => $breakdowns[$prediction->fixture_id]->points ?? null]);
        }

        // Group and knockout fold into the same tournament-wide metrics; an entry with no scored
        // prediction keeps a null total (so the boards hold their "warming up" state).
        $metrics = LeaderboardMetrics::fromBreakdowns($breakdowns);
        $entry->update(['total_points' => $breakdowns === [] ? null : $metrics->points]);

        $this->upsertStandings($entry, $metrics);
    }

    /**
     * The per-prediction breakdown for every one of an entry's predictions whose fixture has an
     * official result, keyed by `fixture_id`. Predictions on a fixture that has no result yet (or
     * no matching fixture) are omitted. This is the pure scoring pass — it writes nothing — shared
     * by the live recompute and the per-matchday reconstruction ({@see MatchdayLeaderboard}).
     *
     * @param  array<int, Fixture>  $fixturesById
     * @return array<int, PredictionBreakdown>
     */
    public function breakdownsByFixture(Entry $entry, array $fixturesById, ScoringRules $rules, ScoringConfig $config): array
    {
        $breakdowns = [];

        foreach ($entry->groupPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;

            if ($fixture !== null && $this->hasOfficialResult($fixture)) {
                $breakdowns[$prediction->fixture_id] = $rules->evaluateGroup($prediction, $fixture, $config);
            }
        }

        foreach ($entry->knockoutPredictions as $prediction) {
            $fixture = $fixturesById[$prediction->fixture_id] ?? null;

            if ($fixture !== null && $this->hasOfficialResult($fixture)) {
                $breakdowns[$prediction->fixture_id] = $rules->evaluateKnockout($prediction, $fixture, $config);
            }
        }

        return $breakdowns;
    }

    /**
     * Writes one standing row per leaderboard for the entry. Rows are upserted even for unscored
     * entries (value 0), so every board always has a full set of rows and the "points unlock as
     * results land" empty state holds. Ranks are left to {@see RankSnapshotter}.
     */
    private function upsertStandings(Entry $entry, LeaderboardMetrics $metrics): void
    {
        foreach (LeaderboardCategory::cases() as $category) {
            $entry->standings()->updateOrCreate(
                ['category' => $category],
                [
                    'value' => $category->valueFor($metrics),
                    'tiebreaker' => $category->tiebreakerFor($metrics),
                ],
            );
        }
    }

    private function hasOfficialResult(Fixture $fixture): bool
    {
        return $fixture->home_goals !== null && $fixture->away_goals !== null;
    }
}
