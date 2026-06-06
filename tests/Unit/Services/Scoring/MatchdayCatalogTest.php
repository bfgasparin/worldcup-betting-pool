<?php

namespace Tests\Unit\Services\Scoring;

use App\Models\Fixture;
use App\Models\Tournament;
use App\Services\Scoring\Matchday;
use App\Services\Scoring\MatchdayCatalog;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MatchdayCatalogTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private MatchdayCatalog $catalog;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->catalog = new MatchdayCatalog;
    }

    public function test_it_returns_nine_matchdays_in_competition_order(): void
    {
        $matchdays = $this->catalog->forTournament($this->tournament);

        $this->assertContainsOnlyInstancesOf(Matchday::class, $matchdays);
        $this->assertSame([
            'group-1',
            'group-2',
            'group-3',
            'round_of_32',
            'round_of_16',
            'quarter_finals',
            'semi_finals',
            'third_place',
            'final',
        ], array_map(fn (Matchday $m): string => $m->key, $matchdays));
    }

    public function test_group_matchdays_each_hold_two_fixtures_per_group(): void
    {
        $matchdays = $this->matchdaysByKey();

        // 12 groups × 2 fixtures per matchday = 24, three times over.
        $this->assertCount(24, $matchdays['group-1']->fixtureIds);
        $this->assertCount(24, $matchdays['group-2']->fixtureIds);
        $this->assertCount(24, $matchdays['group-3']->fixtureIds);

        // Every group fixture is covered exactly once across the three group matchdays.
        $covered = array_merge(
            $matchdays['group-1']->fixtureIds,
            $matchdays['group-2']->fixtureIds,
            $matchdays['group-3']->fixtureIds,
        );
        $this->assertCount(72, $covered);
        $this->assertCount(72, array_unique($covered));

        // Group 'group' matchdays only ever contain group-stage fixtures.
        $this->assertSame('group', $matchdays['group-1']->kind);
    }

    public function test_group_matchday_one_precedes_matchday_two_for_every_group(): void
    {
        $matchdays = $this->matchdaysByKey();

        $latestInMd1 = Fixture::whereIn('id', $matchdays['group-1']->fixtureIds)->max('kicks_off_at');
        $earliestInMd2 = Fixture::whereIn('id', $matchdays['group-2']->fixtureIds)->min('kicks_off_at');

        // The whole second round kicks off after the whole first round.
        $this->assertGreaterThan($latestInMd1, $earliestInMd2);
    }

    public function test_knockout_matchdays_map_one_per_phase(): void
    {
        $matchdays = $this->matchdaysByKey();

        $this->assertCount(16, $matchdays['round_of_32']->fixtureIds);
        $this->assertCount(8, $matchdays['round_of_16']->fixtureIds);
        $this->assertCount(4, $matchdays['quarter_finals']->fixtureIds);
        $this->assertCount(2, $matchdays['semi_finals']->fixtureIds);
        $this->assertCount(1, $matchdays['third_place']->fixtureIds);
        $this->assertCount(1, $matchdays['final']->fixtureIds);

        $this->assertSame('knockout', $matchdays['round_of_32']->kind);
        $this->assertSame('Round of 32', $matchdays['round_of_32']->label);
    }

    public function test_group_matchdays_are_labelled_for_players(): void
    {
        $matchdays = $this->matchdaysByKey();

        $this->assertSame('Matchday 1', $matchdays['group-1']->label);
        $this->assertSame('MD1', $matchdays['group-1']->shortLabel);
    }

    public function test_fixture_index_maps_every_fixture_to_its_matchday(): void
    {
        $index = $this->catalog->fixtureIndex($this->tournament);

        // Every group + knockout fixture is covered exactly once (72 + 32).
        $this->assertCount(104, $index);

        // Each id resolves to the descriptor of the matchday that owns it.
        foreach ($this->catalog->forTournament($this->tournament) as $matchday) {
            foreach ($matchday->fixtureIds as $fixtureId) {
                $this->assertSame([
                    'key' => $matchday->key,
                    'label' => $matchday->label,
                    'short_label' => $matchday->shortLabel,
                    'kind' => $matchday->kind,
                ], $index[$fixtureId]);
            }
        }

        // Spot-check the two ends of the timeline.
        $firstGroupFixture = Fixture::whereNotNull('group_id')->orderBy('match_number')->firstOrFail();
        $this->assertSame('group-1', $index[$firstGroupFixture->id]['key']);

        $finalFixture = Fixture::orderByDesc('match_number')->firstOrFail();
        $this->assertSame('final', $index[$finalFixture->id]['key']);
        $this->assertSame('knockout', $index[$finalFixture->id]['kind']);
    }

    public function test_descriptors_list_the_matchdays_without_fixture_ids(): void
    {
        $descriptors = $this->catalog->descriptors($this->tournament);

        // Same nine matchdays, in the same competition order as forTournament().
        $this->assertSame([
            'group-1',
            'group-2',
            'group-3',
            'round_of_32',
            'round_of_16',
            'quarter_finals',
            'semi_finals',
            'third_place',
            'final',
        ], array_column($descriptors, 'key'));

        // A descriptor carries only the display fields — never fixture ids or leaderboard status.
        $this->assertSame(['key', 'label', 'short_label', 'kind'], array_keys($descriptors[0]));
        $this->assertSame('Matchday 1', $descriptors[0]['label']);
        $this->assertSame('MD1', $descriptors[0]['short_label']);
        $this->assertSame('group', $descriptors[0]['kind']);
        $this->assertSame('knockout', $descriptors[3]['kind']);
    }

    /**
     * @return array<string, Matchday>
     */
    private function matchdaysByKey(): array
    {
        $byKey = [];

        foreach ($this->catalog->forTournament($this->tournament) as $matchday) {
            $byKey[$matchday->key] = $matchday;
        }

        return $byKey;
    }
}
