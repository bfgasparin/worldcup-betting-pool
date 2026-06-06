<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\FixtureStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Scoring\MatchdayCatalog;
use App\Services\Scoring\MatchdayLeaderboard;
use App\Services\Scoring\MatchdayLeaderboardView;
use App\Services\Scoring\ScoreEngine;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class MatchdayLeaderboardTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $pool;

    private Entry $me;

    private Entry $rival;

    private MatchdayLeaderboard $builder;

    private MatchdayCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->catalog = new MatchdayCatalog;
        $this->builder = new MatchdayLeaderboard($this->catalog, new ScoreEngine);

        // Me predicts the seed order (the official outcome), the rival predicts the reverse.
        $this->me = Entry::factory()->for($this->pool)->for(User::factory()->create(['name' => 'Me']))->create();
        $this->rival = Entry::factory()->for($this->pool)->for(User::factory()->create(['name' => 'Rival']))->create();
        $this->predictAllGroups($this->me, $this->tournament, $this->seedOrderScores());
        $this->predictAllGroups($this->rival, $this->tournament, $this->reverseSeedScores());
    }

    public function test_current_matchday_reports_live_standings_and_per_matchday_cards(): void
    {
        $this->recordResultsFor('group-1', $this->seedOrderScores());
        (new ScoreEngine)->recompute($this->pool);

        $view = $this->builder->build($this->pool, $this->me->user_id, null);

        // With only matchday 1 settled, it is the current matchday and the default selection.
        $this->assertSame('group-1', $view->selectedKey);

        $overall = $this->board($view, 'overall');
        $this->assertSame('Me', $overall['rows'][0]['name'] === 'You' ? 'Me' : $overall['rows'][0]['name']);
        $this->assertTrue($overall['rows'][0]['is_me']);
        $this->assertGreaterThan($overall['rows'][1]['primary_value'], $overall['rows'][0]['primary_value']);

        // Cards for matchday 1, expressed in Overall points.
        $stats = $overall['matchday_stats'];
        $this->assertSame($this->me->id, $stats['you']['entry_id']);
        $this->assertSame($this->me->id, $stats['top']['entry_id']);
        $this->assertSame($this->rival->id, $stats['lowest']['entry_id']);
        $this->assertGreaterThan($stats['lowest']['value'], $stats['top']['value']);
        $this->assertSame($stats['top']['value'], $stats['you']['value']);
    }

    public function test_a_past_matchday_is_frozen_at_its_own_end(): void
    {
        // Settle matchday 1, then matchday 2; both played out in seed order.
        $this->recordResultsFor('group-1', $this->seedOrderScores());
        $this->recordResultsFor('group-2', $this->seedOrderScores());
        (new ScoreEngine)->recompute($this->pool);

        $afterMd1 = $this->builder->build($this->pool, $this->me->user_id, 'group-1');
        $afterMd2 = $this->builder->build($this->pool, $this->me->user_id, 'group-2');

        $myMd1 = $this->myRow($afterMd1, 'overall')['primary_value'];
        $myMd2 = $this->myRow($afterMd2, 'overall')['primary_value'];

        // Matchday 2 is the current view; matchday 1 is a frozen, smaller historical snapshot.
        $this->assertGreaterThan(0, $myMd1);
        $this->assertGreaterThan($myMd1, $myMd2);

        // The matchday-2 card shows only what was earned in matchday 2 (the cumulative gain).
        $md2Card = $this->board($afterMd2, 'overall')['matchday_stats']['you']['value'];
        $this->assertSame($myMd2 - $myMd1, $md2Card);
    }

    public function test_matchday_descriptors_report_status_and_the_current_marker(): void
    {
        $this->recordResultsFor('group-1', $this->seedOrderScores());
        (new ScoreEngine)->recompute($this->pool);

        $view = $this->builder->build($this->pool, $this->me->user_id, null);
        $byKey = collect($view->matchdays)->keyBy('key');

        $this->assertSame('complete', $byKey['group-1']['status']);
        $this->assertTrue($byKey['group-1']['is_current']);
        $this->assertSame('upcoming', $byKey['group-2']['status']);
        $this->assertFalse($byKey['group-2']['is_current']);
        $this->assertSame('upcoming', $byKey['final']['status']);
    }

    public function test_requesting_an_unplayed_future_matchday_falls_back_to_current(): void
    {
        $this->recordResultsFor('group-1', $this->seedOrderScores());
        (new ScoreEngine)->recompute($this->pool);

        $view = $this->builder->build($this->pool, $this->me->user_id, 'final');

        $this->assertSame('group-1', $view->selectedKey);
    }

    /**
     * The inverse of {@see seedOrderScores()} — the worse-seeded team wins, so every pick is the
     * wrong outcome against an official seed-order result.
     *
     * @return callable(int, int): array{int, int}
     */
    private function reverseSeedScores(): callable
    {
        return fn (int $homePosition, int $awayPosition): array => $homePosition < $awayPosition
            ? [0, 1]
            : [1, 0];
    }

    /**
     * Record official results for one matchday's fixtures using a position-based rule.
     *
     * @param  callable(int, int): array{int, int}  $rule
     */
    private function recordResultsFor(string $matchdayKey, callable $rule): void
    {
        $positions = [];
        foreach ($this->tournament->groups()->with('teams')->get() as $group) {
            foreach ($group->teams as $team) {
                $positions[$team->id] = $team->pivot->position;
            }
        }

        $fixtureIds = collect($this->catalog->forTournament($this->tournament))
            ->firstWhere('key', $matchdayKey)
            ->fixtureIds;

        foreach (Fixture::whereIn('id', $fixtureIds)->get() as $fixture) {
            [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);

            $fixture->update([
                'home_goals' => $home,
                'away_goals' => $away,
                'winner_team_id' => $home === $away ? null : ($home > $away ? $fixture->home_team_id : $fixture->away_team_id),
                'status' => FixtureStatus::Finished,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function board(MatchdayLeaderboardView $view, string $key): array
    {
        return collect($view->boards)->firstWhere('key', $key);
    }

    /**
     * @return array<string, mixed>
     */
    private function myRow(MatchdayLeaderboardView $view, string $boardKey): array
    {
        return collect($this->board($view, $boardKey)['rows'])->firstWhere('is_me', true);
    }
}
