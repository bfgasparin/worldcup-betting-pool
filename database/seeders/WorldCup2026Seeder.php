<?php

namespace Database\Seeders;

use App\Enums\FeederOutcome;
use App\Enums\PhaseKey;
use App\Enums\PhaseType;
use App\Enums\ScoringStrategy;
use App\Enums\Sport;
use App\Enums\TournamentStatus;
use App\Models\Fixture;
use App\Models\Group;
use App\Models\Phase;
use App\Models\Team;
use App\Models\Tournament;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorldCup2026Seeder extends Seeder
{
    /**
     * The 12 groups (A–L), each with its four drawn teams in pot order.
     *
     * NOTE: the group draw below is provisional/illustrative for development. The team
     * list is real, but slot assignments must be reconciled with FIFA's official draw
     * before going live. Unknown qualifiers are seeded as placeholder teams.
     *
     * @var array<string, list<array{name: string, code: ?string, placeholder?: bool}>>
     */
    private const GROUPS = [
        'A' => [['name' => 'Mexico', 'code' => 'MEX'], ['name' => 'Croatia', 'code' => 'CRO'], ['name' => 'Ecuador', 'code' => 'ECU'], ['name' => 'Qatar', 'code' => 'QAT']],
        'B' => [['name' => 'Canada', 'code' => 'CAN'], ['name' => 'Belgium', 'code' => 'BEL'], ['name' => 'Egypt', 'code' => 'EGY'], ['name' => 'Saudi Arabia', 'code' => 'KSA']],
        'C' => [['name' => 'United States', 'code' => 'USA'], ['name' => 'Netherlands', 'code' => 'NED'], ['name' => 'Senegal', 'code' => 'SEN'], ['name' => 'Iran', 'code' => 'IRN']],
        'D' => [['name' => 'Argentina', 'code' => 'ARG'], ['name' => 'Denmark', 'code' => 'DEN'], ['name' => 'Nigeria', 'code' => 'NGA'], ['name' => 'Australia', 'code' => 'AUS']],
        'E' => [['name' => 'France', 'code' => 'FRA'], ['name' => 'Switzerland', 'code' => 'SUI'], ['name' => 'Algeria', 'code' => 'ALG'], ['name' => 'South Korea', 'code' => 'KOR']],
        'F' => [['name' => 'Brazil', 'code' => 'BRA'], ['name' => 'Austria', 'code' => 'AUT'], ['name' => 'Tunisia', 'code' => 'TUN'], ['name' => 'Japan', 'code' => 'JPN']],
        'G' => [['name' => 'Spain', 'code' => 'ESP'], ['name' => 'Poland', 'code' => 'POL'], ['name' => 'Ghana', 'code' => 'GHA'], ['name' => 'Iraq', 'code' => 'IRQ']],
        'H' => [['name' => 'England', 'code' => 'ENG'], ['name' => 'Norway', 'code' => 'NOR'], ['name' => 'Cameroon', 'code' => 'CMR'], ['name' => 'Uzbekistan', 'code' => 'UZB']],
        'I' => [['name' => 'Portugal', 'code' => 'POR'], ['name' => 'Uruguay', 'code' => 'URU'], ['name' => 'Ivory Coast', 'code' => 'CIV'], ['name' => 'Panama', 'code' => 'PAN']],
        'J' => [['name' => 'Germany', 'code' => 'GER'], ['name' => 'Colombia', 'code' => 'COL'], ['name' => 'Morocco', 'code' => 'MAR'], ['name' => 'Costa Rica', 'code' => 'CRC']],
        'K' => [['name' => 'Italy', 'code' => 'ITA'], ['name' => 'Paraguay', 'code' => 'PAR'], ['name' => 'New Zealand', 'code' => 'NZL'], ['name' => 'UEFA Play-off Winner', 'code' => null, 'placeholder' => true]],
        'L' => [['name' => 'Turkey', 'code' => 'TUR'], ['name' => 'Scotland', 'code' => 'SCO'], ['name' => 'Jamaica', 'code' => 'JAM'], ['name' => 'Inter-confederation Play-off Winner', 'code' => null, 'placeholder' => true]],
    ];

    /**
     * Round-robin pairings (by group position 1–4): each team plays the other three once.
     *
     * @var list<array{int, int}>
     */
    private const GROUP_PAIRINGS = [[1, 2], [3, 4], [1, 3], [4, 2], [4, 1], [2, 3]];

    /**
     * The 7 phases in progression order.
     *
     * @var list<array{key: PhaseKey, type: PhaseType, name: string}>
     */
    private const PHASES = [
        ['key' => PhaseKey::Group, 'type' => PhaseType::Group, 'name' => 'Group Stage'],
        ['key' => PhaseKey::RoundOf32, 'type' => PhaseType::Knockout, 'name' => 'Round of 32'],
        ['key' => PhaseKey::RoundOf16, 'type' => PhaseType::Knockout, 'name' => 'Round of 16'],
        ['key' => PhaseKey::QuarterFinals, 'type' => PhaseType::Knockout, 'name' => 'Quarter-finals'],
        ['key' => PhaseKey::SemiFinals, 'type' => PhaseType::Knockout, 'name' => 'Semi-finals'],
        ['key' => PhaseKey::ThirdPlace, 'type' => PhaseType::Knockout, 'name' => 'Third-place Play-off'],
        ['key' => PhaseKey::Final, 'type' => PhaseType::Knockout, 'name' => 'Final'],
    ];

    /**
     * Seed the World Cup 2026 tournament structure idempotently.
     */
    public function run(): void
    {
        DB::transaction(function () {
            $tournament = $this->seedTournament();
            $phases = $this->seedPhases($tournament);
            $groupTeams = $this->seedGroupsAndTeams($tournament);
            $this->seedGroupFixtures($tournament, $phases[PhaseKey::Group->value], $groupTeams);
            $this->seedKnockoutFixtures($tournament, $phases);
        });
    }

    private function seedTournament(): Tournament
    {
        return Tournament::updateOrCreate(
            ['slug' => 'world-cup-2026'],
            [
                'name' => 'World Cup 2026',
                'sport' => Sport::Soccer,
                'status' => TournamentStatus::Open,
                'scoring_strategy' => ScoringStrategy::WorldCupStandard,
                'scoring_config' => [
                    'group' => [
                        'exact_score' => 20,
                        'winner_and_one_team_exact_goals' => 15,
                        'correct_outcome_wrong_goals' => 10,
                        'one_team_exact_goals_wrong_outcome' => 5,
                    ],
                    'knockout' => [
                        'team_reaches_phase' => 10,
                        'team_goal_count_bonus' => 5,
                        'champion' => 30,
                    ],
                ],
                'predictions_lock_at' => '2026-06-11 16:00:00',
                'starts_on' => '2026-06-11',
                'ends_on' => '2026-07-19',
            ],
        );
    }

    /**
     * @return array<string, Phase> keyed by phase key value
     */
    private function seedPhases(Tournament $tournament): array
    {
        $phases = [];

        foreach (self::PHASES as $index => $phase) {
            $phases[$phase['key']->value] = Phase::updateOrCreate(
                ['tournament_id' => $tournament->id, 'key' => $phase['key']->value],
                ['type' => $phase['type']->value, 'name' => $phase['name'], 'sort_order' => $index + 1],
            );
        }

        return $phases;
    }

    /**
     * @return array<string, array<int, int>> team ids keyed by [group name][position]
     */
    private function seedGroupsAndTeams(Tournament $tournament): array
    {
        $groupTeams = [];
        $sortOrder = 1;

        foreach (self::GROUPS as $name => $teams) {
            $group = Group::updateOrCreate(
                ['tournament_id' => $tournament->id, 'name' => $name],
                ['sort_order' => $sortOrder++],
            );

            $sync = [];

            foreach ($teams as $position => $definition) {
                $team = Team::updateOrCreate(
                    ['name' => $definition['name']],
                    ['code' => $definition['code'], 'is_placeholder' => $definition['placeholder'] ?? false],
                );

                $sync[$team->id] = ['position' => $position + 1];
                $groupTeams[$name][$position + 1] = $team->id;
            }

            $group->teams()->sync($sync);
        }

        return $groupTeams;
    }

    /**
     * Build the 72 group fixtures (6 per group), match numbers 1–72.
     *
     * @param  array<string, array<int, int>>  $groupTeams
     */
    private function seedGroupFixtures(Tournament $tournament, Phase $phase, array $groupTeams): void
    {
        $matchNumber = 1;
        $groupIndex = 0;

        foreach (self::GROUPS as $name => $teams) {
            $group = $tournament->groups()->where('name', $name)->firstOrFail();

            foreach (self::GROUP_PAIRINGS as [$home, $away]) {
                Fixture::updateOrCreate(
                    ['tournament_id' => $tournament->id, 'match_number' => $matchNumber++],
                    [
                        'phase_id' => $phase->id,
                        'group_id' => $group->id,
                        'home_team_id' => $groupTeams[$name][$home],
                        'away_team_id' => $groupTeams[$name][$away],
                    ],
                );
            }

            $groupIndex++;
        }
    }

    /**
     * Build the 32 knockout fixtures (match numbers 73–104) and wire their feeders.
     *
     * @param  array<string, Phase>  $phases
     */
    private function seedKnockoutFixtures(Tournament $tournament, array $phases): void
    {
        // Pass 1: create every bracket slot with its display labels (no feeders yet).
        $slots = $this->bracketDefinition();
        $fixtures = [];

        foreach ($slots as $slot) {
            $fixtures[$slot['slot']] = Fixture::updateOrCreate(
                ['tournament_id' => $tournament->id, 'match_number' => $slot['match_number']],
                [
                    'phase_id' => $phases[$slot['phase']->value]->id,
                    'bracket_slot' => $slot['slot'],
                    'home_placeholder_label' => $slot['home_label'],
                    'away_placeholder_label' => $slot['away_label'],
                ],
            );
        }

        // Pass 2: link knockout fixtures fed by prior knockout fixtures.
        foreach ($slots as $slot) {
            if ($slot['home_feeder'] === null && $slot['away_feeder'] === null) {
                continue;
            }

            $fixtures[$slot['slot']]->update([
                'home_feeder_fixture_id' => $fixtures[$slot['home_feeder'][0]]->id,
                'home_feeder_outcome' => $slot['home_feeder'][1]->value,
                'away_feeder_fixture_id' => $fixtures[$slot['away_feeder'][0]]->id,
                'away_feeder_outcome' => $slot['away_feeder'][1]->value,
            ]);
        }
    }

    /**
     * Declarative bracket: 16 R32 slots fed from group standings, then a single-elimination
     * tree (R16 → QF → SF → Final) plus the third-place play-off off the semifinal losers.
     *
     * @return list<array{
     *     slot: string,
     *     phase: PhaseKey,
     *     match_number: int,
     *     home_label: string,
     *     away_label: string,
     *     home_feeder: ?array{string, FeederOutcome},
     *     away_feeder: ?array{string, FeederOutcome}
     * }>
     */
    private function bracketDefinition(): array
    {
        $slots = [];

        // Round of 32 — fed from group standings (no fixture feeders). Pairings are provisional.
        $r32 = [
            ['Winner Group A', '3rd Place 1'], ['Winner Group B', '3rd Place 2'],
            ['Winner Group C', '3rd Place 3'], ['Winner Group D', '3rd Place 4'],
            ['Winner Group E', '3rd Place 5'], ['Winner Group F', '3rd Place 6'],
            ['Winner Group G', '3rd Place 7'], ['Winner Group H', '3rd Place 8'],
            ['Winner Group I', 'Runner-up Group J'], ['Winner Group J', 'Runner-up Group I'],
            ['Winner Group K', 'Runner-up Group L'], ['Winner Group L', 'Runner-up Group K'],
            ['Runner-up Group A', 'Runner-up Group B'], ['Runner-up Group C', 'Runner-up Group D'],
            ['Runner-up Group E', 'Runner-up Group F'], ['Runner-up Group G', 'Runner-up Group H'],
        ];

        foreach ($r32 as $index => [$home, $away]) {
            $slots[] = [
                'slot' => 'R32-'.($index + 1),
                'phase' => PhaseKey::RoundOf32,
                'match_number' => 73 + $index,
                'home_label' => $home,
                'away_label' => $away,
                'home_feeder' => null,
                'away_feeder' => null,
            ];
        }

        // Round of 16 — winners of consecutive R32 slots.
        for ($i = 0; $i < 8; $i++) {
            $home = 'R32-'.($i * 2 + 1);
            $away = 'R32-'.($i * 2 + 2);
            $slots[] = $this->feederSlot('R16-'.($i + 1), PhaseKey::RoundOf16, 89 + $i, $home, $away);
        }

        // Quarter-finals — winners of consecutive R16 slots.
        for ($i = 0; $i < 4; $i++) {
            $home = 'R16-'.($i * 2 + 1);
            $away = 'R16-'.($i * 2 + 2);
            $slots[] = $this->feederSlot('QF-'.($i + 1), PhaseKey::QuarterFinals, 97 + $i, $home, $away);
        }

        // Semi-finals.
        $slots[] = $this->feederSlot('SF-1', PhaseKey::SemiFinals, 101, 'QF-1', 'QF-2');
        $slots[] = $this->feederSlot('SF-2', PhaseKey::SemiFinals, 102, 'QF-3', 'QF-4');

        // Third-place play-off — the two semifinal losers.
        $slots[] = [
            'slot' => 'TP',
            'phase' => PhaseKey::ThirdPlace,
            'match_number' => 103,
            'home_label' => 'Loser SF-1',
            'away_label' => 'Loser SF-2',
            'home_feeder' => ['SF-1', FeederOutcome::Loser],
            'away_feeder' => ['SF-2', FeederOutcome::Loser],
        ];

        // Final — the two semifinal winners.
        $slots[] = $this->feederSlot('F', PhaseKey::Final, 104, 'SF-1', 'SF-2');

        return $slots;
    }

    /**
     * Build a knockout slot fed by the winners of two prior fixtures.
     *
     * @return array{
     *     slot: string,
     *     phase: PhaseKey,
     *     match_number: int,
     *     home_label: string,
     *     away_label: string,
     *     home_feeder: array{string, FeederOutcome},
     *     away_feeder: array{string, FeederOutcome}
     * }
     */
    private function feederSlot(string $slot, PhaseKey $phase, int $matchNumber, string $homeSlot, string $awaySlot): array
    {
        return [
            'slot' => $slot,
            'phase' => $phase,
            'match_number' => $matchNumber,
            'home_label' => 'Winner '.$homeSlot,
            'away_label' => 'Winner '.$awaySlot,
            'home_feeder' => [$homeSlot, FeederOutcome::Winner],
            'away_feeder' => [$awaySlot, FeederOutcome::Winner],
        ];
    }
}
