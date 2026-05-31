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
use App\Services\Predictions\ThirdPlaceAllocation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class WorldCup2026Seeder extends Seeder
{
    /**
     * The 12 groups (A–L) from the official FIFA World Cup 2026 final draw (Washington D.C.,
     * December 5, 2025), each with its four teams in seeded position order (1 = host/top seed).
     * Hosts are fixed: Mexico A1, Canada B1, United States D1.
     *
     * @var array<string, list<array{name: string, code: ?string, placeholder?: bool}>>
     */
    private const GROUPS = [
        'A' => [['name' => 'Mexico', 'code' => 'MEX'], ['name' => 'South Korea', 'code' => 'KOR'], ['name' => 'South Africa', 'code' => 'RSA'], ['name' => 'Czechia', 'code' => 'CZE']],
        'B' => [['name' => 'Canada', 'code' => 'CAN'], ['name' => 'Switzerland', 'code' => 'SUI'], ['name' => 'Qatar', 'code' => 'QAT'], ['name' => 'Bosnia and Herzegovina', 'code' => 'BIH']],
        'C' => [['name' => 'Brazil', 'code' => 'BRA'], ['name' => 'Morocco', 'code' => 'MAR'], ['name' => 'Scotland', 'code' => 'SCO'], ['name' => 'Haiti', 'code' => 'HAI']],
        'D' => [['name' => 'United States', 'code' => 'USA'], ['name' => 'Paraguay', 'code' => 'PAR'], ['name' => 'Australia', 'code' => 'AUS'], ['name' => 'Turkey', 'code' => 'TUR']],
        'E' => [['name' => 'Germany', 'code' => 'GER'], ['name' => 'Ecuador', 'code' => 'ECU'], ['name' => 'Ivory Coast', 'code' => 'CIV'], ['name' => 'Curacao', 'code' => 'CUW']],
        'F' => [['name' => 'Netherlands', 'code' => 'NED'], ['name' => 'Japan', 'code' => 'JPN'], ['name' => 'Tunisia', 'code' => 'TUN'], ['name' => 'Sweden', 'code' => 'SWE']],
        'G' => [['name' => 'Belgium', 'code' => 'BEL'], ['name' => 'Iran', 'code' => 'IRN'], ['name' => 'Egypt', 'code' => 'EGY'], ['name' => 'New Zealand', 'code' => 'NZL']],
        'H' => [['name' => 'Spain', 'code' => 'ESP'], ['name' => 'Uruguay', 'code' => 'URU'], ['name' => 'Saudi Arabia', 'code' => 'KSA'], ['name' => 'Cape Verde', 'code' => 'CPV']],
        'I' => [['name' => 'France', 'code' => 'FRA'], ['name' => 'Senegal', 'code' => 'SEN'], ['name' => 'Norway', 'code' => 'NOR'], ['name' => 'Iraq', 'code' => 'IRQ']],
        'J' => [['name' => 'Argentina', 'code' => 'ARG'], ['name' => 'Austria', 'code' => 'AUT'], ['name' => 'Algeria', 'code' => 'ALG'], ['name' => 'Jordan', 'code' => 'JOR']],
        'K' => [['name' => 'Portugal', 'code' => 'POR'], ['name' => 'Colombia', 'code' => 'COL'], ['name' => 'Uzbekistan', 'code' => 'UZB'], ['name' => 'DR Congo', 'code' => 'COD']],
        'L' => [['name' => 'England', 'code' => 'ENG'], ['name' => 'Croatia', 'code' => 'CRO'], ['name' => 'Panama', 'code' => 'PAN'], ['name' => 'Ghana', 'code' => 'GHA']],
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
     * The official FIFA World Cup 2026 knockout bracket. Match numbers 73–88 are the Round of
     * 32 (fed from the group standings), then a single-elimination tree (R16 → QF → SF → Final,
     * matches 89–104) plus the third-place play-off (103) off the semifinal losers. Winner-vs-
     * third Round-of-32 slots carry the eligible-group set as their label; the resolver fills
     * them via {@see ThirdPlaceAllocation}.
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

        $thirdLabel = fn (int $matchNumber): string => '3rd Group '.implode('/', ThirdPlaceAllocation::ELIGIBLE_GROUPS[$matchNumber]);

        // Round of 32 (matches 73–88) — fed from group standings (no fixture feeders).
        $r32 = [
            73 => ['Runner-up Group A', 'Runner-up Group B'],
            74 => ['Winner Group E', $thirdLabel(74)],
            75 => ['Winner Group F', 'Runner-up Group C'],
            76 => ['Winner Group C', 'Runner-up Group F'],
            77 => ['Winner Group I', $thirdLabel(77)],
            78 => ['Runner-up Group E', 'Runner-up Group I'],
            79 => ['Winner Group A', $thirdLabel(79)],
            80 => ['Winner Group L', $thirdLabel(80)],
            81 => ['Winner Group D', $thirdLabel(81)],
            82 => ['Winner Group G', $thirdLabel(82)],
            83 => ['Runner-up Group K', 'Runner-up Group L'],
            84 => ['Winner Group H', 'Runner-up Group J'],
            85 => ['Winner Group B', $thirdLabel(85)],
            86 => ['Winner Group J', 'Runner-up Group H'],
            87 => ['Winner Group K', $thirdLabel(87)],
            88 => ['Runner-up Group D', 'Runner-up Group G'],
        ];

        foreach ($r32 as $matchNumber => [$home, $away]) {
            $slots[] = [
                'slot' => $this->slotForMatch($matchNumber),
                'phase' => PhaseKey::RoundOf32,
                'match_number' => $matchNumber,
                'home_label' => $home,
                'away_label' => $away,
                'home_feeder' => null,
                'away_feeder' => null,
            ];
        }

        // Single-elimination tree (matches 89–104): match number => [phase, home feeder match, away feeder match].
        $feeders = [
            89 => [PhaseKey::RoundOf16, 74, 77],
            90 => [PhaseKey::RoundOf16, 73, 75],
            91 => [PhaseKey::RoundOf16, 76, 78],
            92 => [PhaseKey::RoundOf16, 79, 80],
            93 => [PhaseKey::RoundOf16, 83, 84],
            94 => [PhaseKey::RoundOf16, 81, 82],
            95 => [PhaseKey::RoundOf16, 86, 88],
            96 => [PhaseKey::RoundOf16, 85, 87],
            97 => [PhaseKey::QuarterFinals, 89, 90],
            98 => [PhaseKey::QuarterFinals, 93, 94],
            99 => [PhaseKey::QuarterFinals, 91, 92],
            100 => [PhaseKey::QuarterFinals, 95, 96],
            101 => [PhaseKey::SemiFinals, 97, 98],
            102 => [PhaseKey::SemiFinals, 99, 100],
            104 => [PhaseKey::Final, 101, 102],
        ];

        foreach ($feeders as $matchNumber => [$phase, $homeMatch, $awayMatch]) {
            $slots[] = $this->feederSlot(
                $this->slotForMatch($matchNumber),
                $phase,
                $matchNumber,
                $this->slotForMatch($homeMatch),
                $this->slotForMatch($awayMatch),
            );
        }

        // Third-place play-off (match 103) — the two semifinal losers.
        $slots[] = [
            'slot' => 'TP',
            'phase' => PhaseKey::ThirdPlace,
            'match_number' => 103,
            'home_label' => 'Loser SF-1',
            'away_label' => 'Loser SF-2',
            'home_feeder' => ['SF-1', FeederOutcome::Loser],
            'away_feeder' => ['SF-2', FeederOutcome::Loser],
        ];

        return $slots;
    }

    /**
     * The internal bracket-slot handle for a knockout match number.
     */
    private function slotForMatch(int $matchNumber): string
    {
        return match (true) {
            $matchNumber >= 73 && $matchNumber <= 88 => 'R32-'.($matchNumber - 72),
            $matchNumber >= 89 && $matchNumber <= 96 => 'R16-'.($matchNumber - 88),
            $matchNumber >= 97 && $matchNumber <= 100 => 'QF-'.($matchNumber - 96),
            $matchNumber >= 101 && $matchNumber <= 102 => 'SF-'.($matchNumber - 100),
            $matchNumber === 103 => 'TP',
            default => 'F',
        };
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
