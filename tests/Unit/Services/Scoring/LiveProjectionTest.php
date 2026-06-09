<?php

namespace Tests\Unit\Services\Scoring;

use App\Enums\FixtureStatus;
use App\Enums\LiveStatus;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\FixtureLiveState;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\LeaderboardStanding;
use App\Models\Pool;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use App\Services\Scoring\LiveProjection;
use App\Services\Scoring\LiveProjectionResult;
use App\Services\Scoring\ScoringConfig;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class LiveProjectionTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Pool $upfront;

    private Pool $phased;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);

        $this->tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();
        $this->upfront = Pool::where('slug', 'world-cup-2026-ffa')->firstOrFail();
        $this->phased = Pool::where('slug', 'world-cup-2026-brothers')->firstOrFail();
    }

    public function test_a_live_group_score_projects_points_and_ranks_without_touching_official_data(): void
    {
        $sharp = $this->entryFor($this->upfront);
        $blunt = $this->entryFor($this->upfront);

        $fixture = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $sharp->id, 'fixture_id' => $fixture->id, 'home_goals' => 2, 'away_goals' => 0]);
        // 1–1 against a 2–0 result: wrong outcome and neither team's goals right → a clean zero.
        GroupPrediction::create(['entry_id' => $blunt->id, 'fixture_id' => $fixture->id, 'home_goals' => 1, 'away_goals' => 1]);

        $this->markFixtureLive($fixture, 2, 0);

        $result = app(LiveProjection::class)->project($this->upfront);
        $overall = collect($result->boards['overall']);

        $sharpRow = $overall->firstWhere('entry_id', $sharp->id);
        $bluntRow = $overall->firstWhere('entry_id', $blunt->id);

        $this->assertGreaterThan(0, $sharpRow['primary_value']);
        $this->assertSame(0, $bluntRow['primary_value']);
        $this->assertSame(1, $sharpRow['rank']);
        $this->assertSame(2, $bluntRow['rank']);

        // Isolation: no official scoring ran.
        $this->assertNull($sharp->fresh()->total_points);
        $this->assertSame(0, LeaderboardStanding::count());
        $this->assertNull($fixture->fresh()->home_goals);
    }

    public function test_live_gain_is_the_projected_value_minus_the_banked_official_value(): void
    {
        $entry = $this->entryFor($this->upfront);

        $fixture = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $entry->id, 'fixture_id' => $fixture->id, 'home_goals' => 2, 'away_goals' => 0]);
        $this->markFixtureLive($fixture, 2, 0);

        // No official results banked yet, so the live gain is the whole projected value — on every board.
        $result = app(LiveProjection::class)->project($this->upfront);
        foreach ($result->boards as $board) {
            $row = collect($board)->firstWhere('entry_id', $entry->id);
            $this->assertSame($row['primary_value'], $row['live_gain']);
        }

        $overall = collect($result->boards['overall'])->firstWhere('entry_id', $entry->id);
        $this->assertGreaterThan(0, $overall['live_gain']);

        // Once points are on the books, the gain is only what the live result adds on top of them.
        $entry->update(['total_points' => 3]);
        $overall = collect(app(LiveProjection::class)->project($this->upfront)->boards['overall'])
            ->firstWhere('entry_id', $entry->id);

        $this->assertSame($overall['primary_value'] - 3, $overall['live_gain']);
    }

    public function test_a_live_group_result_cascades_into_the_upfront_knockout_bracket(): void
    {
        $user = User::factory()->create();
        $entry = Entry::factory()->create(['pool_id' => $this->upfront->id, 'user_id' => $user->id]);

        $this->predictAllGroups($entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($entry, app(BracketResolver::class));

        // The whole group stage plays out live in seed order — the same bracket the entry predicted.
        $this->markAllGroupsLive();

        // A Round-of-32 slot fed by group winners/runners-up (not a best-thirds slot) so it resolves
        // from the live group standings alone.
        $r32 = $this->tournament->knockoutFixtures()
            ->whereNotNull('home_placeholder_label')
            ->where('home_placeholder_label', 'not like', '3rd%')
            ->where('away_placeholder_label', 'not like', '3rd%')
            ->orderBy('match_number')
            ->firstOrFail();

        $beforeR32 = $this->overallPoints(app(LiveProjection::class)->project($this->upfront), $entry);

        $this->markFixtureLive($r32, 1, 0);
        $afterR32 = $this->overallPoints(app(LiveProjection::class)->project($this->upfront), $entry);

        // The knockout prediction could only score because the live group results re-resolved the
        // bracket in-memory and placed the predicted teams into this R32 match.
        $this->assertGreaterThan($beforeR32, $afterR32);

        // Isolation: the official knockout fixture still has no resolved participants.
        $this->assertNull($r32->fresh()->home_team_id);
    }

    public function test_a_live_knockout_holds_the_phased_advancing_bonus_as_pending(): void
    {
        [$home, $away] = $this->tournament->groups()->with('teams')->first()->teams->take(2)->all();

        $ko = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $ko->update(['home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $entry = $this->entryFor($this->phased);
        KnockoutPrediction::create([
            'entry_id' => $entry->id,
            'fixture_id' => $ko->id,
            'predicted_home_team_id' => $home->id,
            'predicted_away_team_id' => $away->id,
            'home_goals' => 3, // deliberately wrong scoreline so only the bonus is in question
            'away_goals' => 3,
            'advancing_team_id' => $home->id, // matches the live leader below
        ]);

        $this->markFixtureLive($ko, 1, 0); // home leads, winner deliberately left unset (held)

        $result = app(LiveProjection::class)->project($this->phased);
        $row = collect($result->boards['overall'])->firstWhere('entry_id', $entry->id);

        $advancingBonus = ScoringConfig::fromPool($this->phased)->knockoutAdvancingBonus();

        // The advancing bonus is HELD out of projected points while the winner is undecided...
        $this->assertSame(0, $row['primary_value']);
        // ...but surfaced as pending so the UI can show "+X if it holds".
        $this->assertSame($advancingBonus, $row['pending_bonus']);
    }

    public function test_projected_payout_maps_projected_rank_to_the_fixed_pot(): void
    {
        $this->upfront->update([
            'entry_price' => 100,
            'currency' => 'BRL',
            'house_fee_percentage' => 0,
            'prize_structure' => [['place' => 1, 'percentage' => 100]],
        ]);

        $winner = $this->entryFor($this->upfront);
        $runnerUp = $this->entryFor($this->upfront);

        $fixture = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $winner->id, 'fixture_id' => $fixture->id, 'home_goals' => 2, 'away_goals' => 0]);
        GroupPrediction::create(['entry_id' => $runnerUp->id, 'fixture_id' => $fixture->id, 'home_goals' => 0, 'away_goals' => 0]);
        $this->markFixtureLive($fixture, 2, 0);

        $overall = collect(app(LiveProjection::class)->project($this->upfront)->boards['overall']);

        // Two players × 100, no house cut → net pot 200, all to first place.
        $this->assertSame(200.0, $overall->firstWhere('entry_id', $winner->id)['projected_prize']);
        $this->assertNull($overall->firstWhere('entry_id', $runnerUp->id)['projected_prize']);
    }

    public function test_free_pools_project_no_payout(): void
    {
        $this->upfront->update(['entry_price' => 0]);
        $entry = $this->entryFor($this->upfront);

        $fixture = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $entry->id, 'fixture_id' => $fixture->id, 'home_goals' => 2, 'away_goals' => 0]);
        $this->markFixtureLive($fixture, 2, 0);

        $row = collect(app(LiveProjection::class)->project($this->upfront)->boards['overall'])->firstWhere('entry_id', $entry->id);

        $this->assertNull($row['projected_prize']);
    }

    public function test_the_projection_is_cached_by_live_version_and_recomputes_after_a_change(): void
    {
        $entry = $this->entryFor($this->upfront);
        $fixture = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $entry->id, 'fixture_id' => $fixture->id, 'home_goals' => 2, 'away_goals' => 0]);
        $state = $this->markFixtureLive($fixture, 1, 0);

        $service = app(LiveProjection::class);
        $first = $service->cachedFor($this->upfront);
        $second = $service->cachedFor($this->upfront);

        // Same live version → served from cache, but always rebuilt from plain cached DATA (not the
        // DTO itself, which a serialising store would round-trip into __PHP_Incomplete_Class). So the
        // two calls are equal-by-value but distinct instances.
        $this->assertInstanceOf(LiveProjectionResult::class, $first);
        $this->assertNotSame($first, $second);
        $this->assertSame($first->version, $second->version);
        $this->assertEquals($first->boards, $second->boards);

        // A live edit advances the version → recompute with the new score.
        $this->travel(1)->minute();
        $state->update(['home_goals' => 2]);

        $third = $service->cachedFor($this->upfront);
        $this->assertNotSame($first->version, $third->version);
        $this->assertGreaterThan(
            $this->overallPoints($first, $entry),
            $this->overallPoints($third, $entry),
        );
    }

    public function test_fixture_picks_expose_scorelines_and_live_points_for_live_fixtures_only(): void
    {
        $sharp = $this->entryFor($this->upfront);
        $blunt = $this->entryFor($this->upfront);

        $live = $this->firstGroupFixture();
        GroupPrediction::create(['entry_id' => $sharp->id, 'fixture_id' => $live->id, 'home_goals' => 2, 'away_goals' => 0]);
        GroupPrediction::create(['entry_id' => $blunt->id, 'fixture_id' => $live->id, 'home_goals' => 1, 'away_goals' => 1]);

        // A second fixture is predicted but never taken live — its picks must never ship (anti-cheat).
        $scheduled = $this->tournament->groups()->orderBy('sort_order')->firstOrFail()
            ->fixtures()->orderBy('match_number')->skip(1)->firstOrFail();
        GroupPrediction::create(['entry_id' => $sharp->id, 'fixture_id' => $scheduled->id, 'home_goals' => 3, 'away_goals' => 3]);

        $this->markFixtureLive($live, 2, 0);

        $picks = app(LiveProjection::class)->project($this->upfront)->fixturePicks;

        $this->assertArrayHasKey($live->id, $picks);
        $this->assertArrayNotHasKey($scheduled->id, $picks);

        $sharpPick = collect($picks[$live->id])->firstWhere('entry_id', $sharp->id);
        $bluntPick = collect($picks[$live->id])->firstWhere('entry_id', $blunt->id);

        $this->assertSame(2, $sharpPick['home_goals']);
        $this->assertSame(0, $sharpPick['away_goals']);
        $this->assertNull($sharpPick['advancing_team_id']);
        // The sharp 2–0 call against a live 2–0 is earning points now; the blunt 1–1 earns nothing.
        $this->assertGreaterThan(0, $sharpPick['points']);
        $this->assertSame(0, $bluntPick['points']);
    }

    public function test_fixture_picks_carry_the_knockout_advancing_pick(): void
    {
        [$home, $away] = $this->tournament->groups()->with('teams')->first()->teams->take(2)->all();

        $ko = $this->tournament->knockoutFixtures()->orderBy('match_number')->firstOrFail();
        $ko->update(['home_team_id' => $home->id, 'away_team_id' => $away->id]);

        $entry = $this->entryFor($this->phased);
        KnockoutPrediction::create([
            'entry_id' => $entry->id,
            'fixture_id' => $ko->id,
            'predicted_home_team_id' => $home->id,
            'predicted_away_team_id' => $away->id,
            'home_goals' => 1,
            'away_goals' => 0,
            'advancing_team_id' => $home->id,
        ]);

        $this->markFixtureLive($ko, 1, 0);

        $picks = app(LiveProjection::class)->project($this->phased)->fixturePicks;
        $pick = collect($picks[$ko->id])->firstWhere('entry_id', $entry->id);

        $this->assertSame($home->id, $pick['advancing_team_id']);
        $this->assertSame(1, $pick['home_goals']);
        $this->assertSame(0, $pick['away_goals']);
        $this->assertIsInt($pick['points']);
    }

    private function entryFor(Pool $pool): Entry
    {
        return Entry::factory()->create([
            'pool_id' => $pool->id,
            'user_id' => User::factory()->create()->id,
        ]);
    }

    private function firstGroupFixture(): Fixture
    {
        return $this->tournament->groups()->orderBy('sort_order')->firstOrFail()
            ->fixtures()->orderBy('match_number')->firstOrFail();
    }

    private function markFixtureLive(Fixture $fixture, int $home, int $away): FixtureLiveState
    {
        $fixture->update(['status' => FixtureStatus::Live]);

        return FixtureLiveState::factory()->for($fixture)->create([
            'status' => LiveStatus::Live,
            'home_goals' => $home,
            'away_goals' => $away,
        ]);
    }

    private function markAllGroupsLive(): void
    {
        $rule = $this->seedOrderScores();

        foreach ($this->tournament->groups()->with('teams')->get() as $group) {
            $positions = $group->teams->mapWithKeys(fn ($team) => [$team->id => $team->pivot->position]);

            foreach ($group->fixtures()->get() as $fixture) {
                [$home, $away] = $rule($positions[$fixture->home_team_id], $positions[$fixture->away_team_id]);
                $this->markFixtureLive($fixture, $home, $away);
            }
        }
    }

    private function overallPoints(LiveProjectionResult $result, Entry $entry): int
    {
        return collect($result->boards['overall'])->firstWhere('entry_id', $entry->id)['primary_value'];
    }
}
