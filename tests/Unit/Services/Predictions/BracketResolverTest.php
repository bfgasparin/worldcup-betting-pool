<?php

namespace Tests\Unit\Services\Predictions;

use App\Enums\FixtureStatus;
use App\Models\Entry;
use App\Models\KnockoutPrediction;
use App\Models\Pool;
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

    private Pool $pool;

    private Entry $entry;

    private BracketResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->pool = $this->tournament->pools()->firstOrFail();
        $this->entry = Entry::factory()->for($this->pool)->for(User::factory())->create();
        $this->resolver = new BracketResolver;
    }

    public function test_resolves_round_of_32_placeholder_labels_to_teams(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $resolved = $this->resolver->resolve($this->entry)->resolved;

        $r32_1 = $this->knockoutFixture($this->tournament, 'R32-1'); // M73: Runner-up Group A vs Runner-up Group B
        $r32_7 = $this->knockoutFixture($this->tournament, 'R32-7'); // M79: Winner Group A vs 3rd Group C/E/F/H/I

        // Runner-up == position 2; Winner == position 1. With all thirds tied, A–H qualify by
        // group order and the official table sends group H's third into the M79 slot.
        $this->assertSame($this->groupTeam('A', 2)->id, $resolved[$r32_1->id]['home']);
        $this->assertSame($this->groupTeam('B', 2)->id, $resolved[$r32_1->id]['away']);
        $this->assertSame($this->groupTeam('A', 1)->id, $resolved[$r32_7->id]['home']);
        $this->assertSame($this->groupTeam('H', 3)->id, $resolved[$r32_7->id]['away']);
    }

    public function test_a_third_never_meets_the_winner_of_its_own_group(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $resolved = $this->resolver->resolve($this->entry)->resolved;
        $teamGroup = $this->tournament->groups()->with('teams')->get()
            ->flatMap(fn ($group) => $group->teams->map(fn ($team) => [$team->id, $group->name]))
            ->mapWithKeys(fn ($pair) => [$pair[0] => $pair[1]]);

        foreach ($this->tournament->knockoutFixtures()->where('match_number', '<=', 88)->get() as $fixture) {
            $home = $resolved[$fixture->id]['home'];
            $away = $resolved[$fixture->id]['away'];

            if ($home !== null && $away !== null) {
                $this->assertNotSame(
                    $teamGroup[$home],
                    $teamGroup[$away],
                    "Match {$fixture->match_number} pairs two teams from the same group.",
                );
            }
        }
    }

    public function test_ranks_eight_best_thirds_by_record_then_group_order(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        // Boost group L's third (position 3) with a big win over its 4th, so it outranks the
        // (otherwise tied) thirds; the rest of the group stays in seed order.
        $this->predictGroup($this->entry, $this->tournament, 'L', function (int $home, int $away): array {
            if ($home === 3 && $away === 4) {
                return [5, 0];
            }

            if ($home === 4 && $away === 3) {
                return [0, 5];
            }

            return $home < $away ? [1, 0] : [0, 1];
        });

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

        // Following the home side down the official bracket: SF-1's half resolves to the Group E
        // winner and SF-2's half to the Group C winner.
        $this->assertSame($this->groupTeam('E', 1)->id, $final->predicted_home_team_id);
        $this->assertSame($this->groupTeam('C', 1)->id, $final->predicted_away_team_id);
    }

    public function test_third_place_play_off_uses_the_semifinal_losers(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $this->resolver);

        $thirdPlace = $this->prediction('TP');

        // With every home team advancing, the SF losers are the SF away slots: the Group K
        // runner-up (SF-1 away) and the Group J winner (SF-2 away).
        $this->assertSame($this->groupTeam('K', 2)->id, $thirdPlace->predicted_home_team_id);
        $this->assertSame($this->groupTeam('J', 1)->id, $thirdPlace->predicted_away_team_id);
    }

    public function test_round_of_32_is_unresolved_when_groups_are_incomplete(): void
    {
        // Only group A is fully predicted.
        $this->predictGroup($this->entry, $this->tournament, 'A', $this->seedOrderScores());

        $bracket = $this->resolver->resolve($this->entry);

        $r32_7 = $this->knockoutFixture($this->tournament, 'R32-7'); // M79: Winner Group A vs 3rd Group …
        $r32_2 = $this->knockoutFixture($this->tournament, 'R32-2'); // M74: Winner Group E vs 3rd Group …

        $this->assertNull($bracket->rankedThirds); // thirds need every group complete
        $this->assertSame($this->groupTeam('A', 1)->id, $bracket->resolved[$r32_7->id]['home']);
        $this->assertNull($bracket->resolved[$r32_7->id]['away']); // the third slot is unknown
        $this->assertNull($bracket->resolved[$r32_2->id]['home']); // Group E not predicted
    }

    public function test_cascade_invalidation_clears_stale_downstream_picks(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());
        $this->advanceAllHome($this->entry, $this->resolver);

        // R32-7 (M79) is the Group A winner's slot; with home advancing it feeds R16-4 (M92).
        $oldGroupAWinner = $this->groupTeam('A', 1)->id;
        $this->assertSame($oldGroupAWinner, $this->prediction('R32-7')->advancing_team_id);
        $this->assertSame($oldGroupAWinner, $this->prediction('R16-4')->predicted_home_team_id);

        // Flip group A so position 2 wins the group and the old winner (position 1) drops to
        // runner-up: position 2 beats position 1; otherwise the better seed wins.
        $this->predictGroup($this->entry, $this->tournament, 'A', function (int $home, int $away): array {
            $winner = (min($home, $away) === 1 && max($home, $away) === 2)
                ? 2
                : min($home, $away);

            return $winner === $home ? [1, 0] : [0, 1];
        });
        $this->resolver->persist($this->entry);

        $r32_7 = $this->prediction('R32-7');
        $this->assertSame($this->groupTeam('A', 2)->id, $r32_7->predicted_home_team_id); // new winner
        $this->assertNull($r32_7->advancing_team_id);   // stale pick cleared
        $this->assertNull($r32_7->home_goals);          // and its score

        // The downstream slot it fed is cleared too (no team advances into it any more).
        $this->assertNull($this->prediction('R16-4')->advancing_team_id);
        $this->assertNull($this->prediction('R16-4')->predicted_home_team_id);

        // A chain untouched by group A keeps its pick (R32-13 = M85 = Winner Group B).
        $this->assertSame($this->groupTeam('B', 1)->id, $this->prediction('R32-13')->advancing_team_id);
    }

    public function test_persist_writes_predicted_teams_and_is_idempotent(): void
    {
        $this->predictAllGroups($this->entry, $this->tournament, $this->seedOrderScores());

        $this->resolver->persist($this->entry);
        $first = $this->prediction('R32-7');

        $this->assertSame(32, $this->entry->knockoutPredictions()->count());
        $this->assertSame($this->groupTeam('A', 1)->id, $first->predicted_home_team_id);

        $this->resolver->persist($this->entry);
        $second = $this->prediction('R32-7');

        $this->assertSame(32, $this->entry->knockoutPredictions()->count());
        $this->assertSame($first->predicted_home_team_id, $second->predicted_home_team_id);
        $this->assertSame($first->predicted_away_team_id, $second->predicted_away_team_id);
    }

    public function test_official_fixture_results_do_not_affect_resolution(): void
    {
        $this->predictGroup($this->entry, $this->tournament, 'A', $this->seedOrderScores());
        $resolvedBefore = $this->resolver->resolve($this->entry)->resolved;
        $r32_7 = $this->knockoutFixture($this->tournament, 'R32-7'); // M79: Winner Group A vs 3rd …

        // Record an official result that contradicts the prediction on a group A fixture.
        $group = $this->tournament->groups()->where('name', 'A')->firstOrFail();
        $group->fixtures()->orderBy('match_number')->first()->update([
            'home_goals' => 0, 'away_goals' => 9, 'status' => FixtureStatus::Finished,
        ]);

        $resolvedAfter = $this->resolver->resolve($this->entry)->resolved;

        $this->assertSame(
            $resolvedBefore[$r32_7->id]['home'],
            $resolvedAfter[$r32_7->id]['home'],
        );
        $this->assertSame($this->groupTeam('A', 1)->id, $resolvedAfter[$r32_7->id]['home']);
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
