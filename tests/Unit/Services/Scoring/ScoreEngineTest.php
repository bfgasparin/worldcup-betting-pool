<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\FixtureStatus;
use App\Models\Entry;
use App\Models\Game;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Predictions\OfficialBracketProjector;
use App\Services\Scoring\ScoreEngine;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithOfficialResults;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class ScoreEngineTest extends TestCase
{
    use InteractsWithOfficialResults;
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Game $game;

    private Entry $entry;

    private ScoreEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->game = $this->tournament->games()->firstOrFail();
        $this->entry = Entry::factory()->for($this->game)->for(User::factory())->create();
        $this->engine = new ScoreEngine;
    }

    public function test_it_scores_group_predictions_across_the_tiers(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $group = $this->tournament->groups()->where('name', 'A')->firstOrFail();
        [$tenPointFixture, $fifteenPointFixture] = $group->fixtures()->orderBy('match_number')->take(2)->get()->all();

        // Right outcome (home win) but neither goal count exact -> 10.
        $this->setPrediction($tenPointFixture->id, 1, 0);
        $tenPointFixture->update(['home_goals' => 2, 'away_goals' => 1, 'status' => FixtureStatus::Finished]);

        // Right outcome (home win) with the home goal count exact -> 15.
        $this->setPrediction($fifteenPointFixture->id, 3, 2);
        $fifteenPointFixture->update(['home_goals' => 3, 'away_goals' => 1, 'status' => FixtureStatus::Finished]);

        $this->engine->recompute($this->game);

        $this->assertSame(10, $this->groupPrediction($tenPointFixture->id)->points_awarded);
        $this->assertSame(15, $this->groupPrediction($fifteenPointFixture->id)->points_awarded);

        // A still-exact fixture (prediction equals the official seed-order result) scores 20.
        $exactFixture = $this->tournament->groups()->where('name', 'B')->firstOrFail()
            ->fixtures()->orderBy('match_number')->first();
        $this->assertSame(20, $this->groupPrediction($exactFixture->id)->points_awarded);

        // total_points equals the sum of every awarded point and is no longer null.
        $sum = (int) $this->entry->groupPredictions()->sum('points_awarded');
        $this->assertSame($sum, $this->entry->fresh()->total_points);
        $this->assertNotNull($this->entry->fresh()->total_points);
    }

    public function test_it_scores_the_knockout_bracket_on_progression(): void
    {
        $resolver = new BracketResolver;
        $projector = new OfficialBracketProjector;

        // The entry predicts the seed-order bracket with home teams advancing; the official
        // results play out identically, so every pick is correct.
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $resolver);
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());
        $this->advanceOfficialHome($this->tournament, $projector);

        $this->engine->recompute($this->game);

        // Round-of-32: both teams correctly placed (+10 each) with both goal counts exact (1-0)
        // (+5 each) = 30. No champion bonus outside the final.
        $r32 = $this->knockoutFixture($this->tournament, 'R32-7');
        $this->assertSame(30, $this->knockoutPrediction($r32->id)->points_awarded);

        // Final: both teams (+10 each), both goal counts exact (+5 each), and the correct champion
        // is picked (+30) = 60.
        $final = $this->knockoutFixture($this->tournament, 'F');
        $this->assertSame(60, $this->knockoutPrediction($final->id)->points_awarded);

        $this->assertNotNull($this->entry->fresh()->total_points);
        $this->assertSame(
            (int) $this->entry->groupPredictions()->sum('points_awarded')
                + (int) $this->entry->knockoutPredictions()->sum('points_awarded'),
            $this->entry->fresh()->total_points,
        );
    }

    public function test_recompute_is_idempotent_and_resets_when_a_result_is_removed(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->recordOfficialGroupResults($this->tournament, $this->seedOrderScores());

        $this->engine->recompute($this->game);
        $firstTotal = $this->entry->fresh()->total_points;

        $this->engine->recompute($this->game);
        $this->assertSame($firstTotal, $this->entry->fresh()->total_points);

        // Remove one group result; that prediction unscored and the total drops by its points.
        $fixture = $this->tournament->groups()->where('name', 'A')->firstOrFail()
            ->fixtures()->orderBy('match_number')->first();
        $removedPoints = $this->groupPrediction($fixture->id)->points_awarded;
        $fixture->update(['home_goals' => null, 'away_goals' => null, 'status' => FixtureStatus::Scheduled]);

        $this->engine->recompute($this->game);

        $this->assertNull($this->groupPrediction($fixture->id)->points_awarded);
        $this->assertSame($firstTotal - $removedPoints, $this->entry->fresh()->total_points);
    }

    public function test_total_points_stays_null_until_a_result_lands(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $this->engine->recompute($this->game);

        $this->assertNull($this->entry->fresh()->total_points);
        $this->assertNull($this->groupPrediction(
            $this->tournament->groups()->where('name', 'A')->firstOrFail()->fixtures()->first()->id,
        )->points_awarded);
    }

    private function setPrediction(int $fixtureId, int $home, int $away): void
    {
        GroupPrediction::updateOrCreate(
            ['entry_id' => $this->entry->id, 'fixture_id' => $fixtureId],
            ['home_goals' => $home, 'away_goals' => $away],
        );
    }

    private function groupPrediction(int $fixtureId): GroupPrediction
    {
        return $this->entry->groupPredictions()->where('fixture_id', $fixtureId)->firstOrFail();
    }

    private function knockoutPrediction(int $fixtureId): KnockoutPrediction
    {
        return $this->entry->knockoutPredictions()->where('fixture_id', $fixtureId)->firstOrFail();
    }
}
