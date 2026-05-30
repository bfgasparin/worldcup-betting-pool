<?php

namespace Tests\Unit\Services\Predictions;

use App\Enums\FixtureStatus;
use App\Models\Entry;
use App\Models\KnockoutPrediction;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use App\Services\Predictions\BracketResolver;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\InteractsWithPredictions;
use Tests\TestCase;

class BracketResolverTest extends TestCase
{
    use InteractsWithPredictions;
    use RefreshDatabase;

    private Tournament $tournament;

    private Entry $entry;

    private BracketResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->entry = Entry::factory()->for($this->tournament)->for(User::factory())->create();
        $this->resolver = new BracketResolver;
    }

    public function test_resolves_round_of_32_placeholder_labels_to_teams(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $resolved = $this->resolver->resolve($this->entry)->resolved;

        $r32_1 = $this->knockoutFixture($this->tournament, 'R32-1');   // Winner Group A vs 3rd Place 1
        $r32_13 = $this->knockoutFixture($this->tournament, 'R32-13'); // Runner-up Group A vs Runner-up Group B

        // Winner Group A == position 1; Runner-up == position 2; 3rd Place 1 == Group A's third
        // (all thirds tie, so the group sort order makes A the first-ranked third).
        $this->assertSame($this->groupTeam('A', 1)->id, $resolved[$r32_1->id]['home']);
        $this->assertSame($this->groupTeam('A', 3)->id, $resolved[$r32_1->id]['away']);
        $this->assertSame($this->groupTeam('A', 2)->id, $resolved[$r32_13->id]['home']);
        $this->assertSame($this->groupTeam('B', 2)->id, $resolved[$r32_13->id]['away']);
    }

    public function test_ranks_eight_best_thirds_by_record_then_group_order(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        // Boost group L's third by a big win over its 4th, so it outranks the (otherwise tied) thirds.
        $this->predictGroup($this->entry, $this->tournament, 'L', [[1, 0], [5, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);

        $ranked = $this->resolver->resolve($this->entry)->rankedThirds;

        $this->assertNotNull($ranked);
        $this->assertCount(8, $ranked);
        // L's third now ranks first; the remaining seven are the next groups by sort order (A..G).
        $this->assertSame($this->groupTeam('L', 3)->id, $ranked[0]);
        $this->assertSame($this->groupTeam('A', 3)->id, $ranked[1]);
        $this->assertSame($this->groupTeam('G', 3)->id, $ranked[7]);
        // Group H's third is the first to miss out.
        $this->assertNotContains($this->groupTeam('H', 3)->id, $ranked);
    }

    public function test_resolves_full_cascade_to_the_final_from_advancing_picks(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $this->resolver);

        $final = $this->prediction('F');

        // Home advances all the way down slot 1 -> Group A winner; the other half -> Group I winner.
        $this->assertSame($this->groupTeam('A', 1)->id, $final->predicted_home_team_id);
        $this->assertSame($this->groupTeam('I', 1)->id, $final->predicted_away_team_id);
    }

    public function test_third_place_play_off_uses_the_semifinal_losers(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $this->resolver);

        $thirdPlace = $this->prediction('TP');

        // With every home team advancing, the SF losers are the SF away slots.
        $this->assertSame($this->groupTeam('E', 1)->id, $thirdPlace->predicted_home_team_id);
        $this->assertSame($this->groupTeam('A', 2)->id, $thirdPlace->predicted_away_team_id);
    }

    public function test_round_of_32_is_unresolved_when_groups_are_incomplete(): void
    {
        // Only group A is fully predicted.
        $this->predictGroup($this->entry, $this->tournament, 'A', $this->seedOrderScores());

        $bracket = $this->resolver->resolve($this->entry);

        $r32_1 = $this->knockoutFixture($this->tournament, 'R32-1'); // Winner Group A vs 3rd Place 1
        $r32_2 = $this->knockoutFixture($this->tournament, 'R32-2'); // Winner Group B vs 3rd Place 2

        $this->assertNull($bracket->rankedThirds); // thirds need every group complete
        $this->assertSame($this->groupTeam('A', 1)->id, $bracket->resolved[$r32_1->id]['home']);
        $this->assertNull($bracket->resolved[$r32_1->id]['away']); // 3rd Place 1 unknown
        $this->assertNull($bracket->resolved[$r32_2->id]['home']); // Group B not predicted
    }

    public function test_cascade_invalidation_clears_stale_downstream_picks(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $this->resolver);

        $oldGroupAWinner = $this->groupTeam('A', 1)->id;
        $this->assertSame($oldGroupAWinner, $this->prediction('F')->predicted_home_team_id);

        // Flip group A so position 2 wins the group and the old winner drops to runner-up.
        $this->predictGroup($this->entry, $this->tournament, 'A', [[0, 1], [1, 0], [1, 0], [0, 1], [0, 1], [1, 0]]);
        $this->resolver->persist($this->entry);

        $r32_1 = $this->prediction('R32-1');
        $this->assertSame($this->groupTeam('A', 2)->id, $r32_1->predicted_home_team_id); // new winner
        $this->assertNull($r32_1->advancing_team_id);   // stale pick cleared
        $this->assertNull($r32_1->home_goals);          // and its score

        // The whole downstream chain that depended on the old winner is cleared too.
        $this->assertNull($this->prediction('R16-1')->advancing_team_id);
        $this->assertNull($this->prediction('F')->advancing_team_id);
        $this->assertNull($this->prediction('F')->predicted_home_team_id);

        // A chain untouched by group A keeps its pick.
        $this->assertSame($this->groupTeam('E', 1)->id, $this->prediction('R32-5')->advancing_team_id);
    }

    public function test_persist_writes_predicted_teams_and_is_idempotent(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $this->resolver->persist($this->entry);
        $first = $this->prediction('R32-1');

        $this->assertSame(32, $this->entry->knockoutPredictions()->count());
        $this->assertSame($this->groupTeam('A', 1)->id, $first->predicted_home_team_id);

        $this->resolver->persist($this->entry);
        $second = $this->prediction('R32-1');

        $this->assertSame(32, $this->entry->knockoutPredictions()->count());
        $this->assertSame($first->predicted_home_team_id, $second->predicted_home_team_id);
        $this->assertSame($first->predicted_away_team_id, $second->predicted_away_team_id);
    }

    public function test_official_fixture_results_do_not_affect_resolution(): void
    {
        $this->predictGroup($this->entry, $this->tournament, 'A', $this->seedOrderScores());
        $resolvedBefore = $this->resolver->resolve($this->entry)->resolved;
        $r32_1 = $this->knockoutFixture($this->tournament, 'R32-1');

        // Record an official result that contradicts the prediction on a group A fixture.
        $group = $this->tournament->groups()->where('name', 'A')->firstOrFail();
        $group->fixtures()->orderBy('match_number')->first()->update([
            'home_goals' => 0, 'away_goals' => 9, 'status' => FixtureStatus::Finished,
        ]);

        $resolvedAfter = $this->resolver->resolve($this->entry)->resolved;

        $this->assertSame(
            $resolvedBefore[$r32_1->id]['home'],
            $resolvedAfter[$r32_1->id]['home'],
        );
        $this->assertSame($this->groupTeam('A', 1)->id, $resolvedAfter[$r32_1->id]['home']);
    }

    private function groupTeam(string $groupName, int $position): Team
    {
        $group = $this->tournament->groups()->where('name', $groupName)->firstOrFail();

        return $group->teams()->wherePivot('position', $position)->firstOrFail();
    }

    private function prediction(string $bracketSlot): KnockoutPrediction
    {
        $fixture = $this->knockoutFixture($this->tournament, $bracketSlot);

        return $this->entry->knockoutPredictions()->where('fixture_id', $fixture->id)->firstOrFail();
    }
}
