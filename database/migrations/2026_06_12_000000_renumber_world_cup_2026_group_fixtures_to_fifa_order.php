<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Renumber the 72 group-stage fixtures of the seeded "World Cup 2026" tournament from the old
 * group-by-group order (A = 1–6, B = 7–12, …) to FIFA's chronological order (match 1 = the opener,
 * then by kickoff). Knockout fixtures (73–104) already use FIFA's numbering and are untouched — as
 * are all the constants keyed off those numbers (ThirdPlaceAllocation, the knockout seeder block).
 *
 * Self-contained query-builder data migration: maps are keyed by the stable
 * (group, home code, away code) identity, not the mutable match number, so it is safe to re-run and
 * a no-op on a database without the tournament (fresh DBs seed the FIFA numbers directly).
 */
return new class extends Migration
{
    /**
     * FIFA chronological match number per group fixture, keyed by "group:home:away".
     */
    private const FIFA_NUMBERS = [
        'A:MEX:RSA' => 1, 'A:KOR:CZE' => 2, 'B:CAN:BIH' => 3, 'D:USA:PAR' => 4, 'B:QAT:SUI' => 5,
        'C:BRA:MAR' => 6, 'C:HAI:SCO' => 7, 'D:AUS:TUR' => 8, 'E:GER:CUW' => 9, 'F:NED:JPN' => 10,
        'E:CIV:ECU' => 11, 'F:SWE:TUN' => 12, 'H:ESP:CPV' => 13, 'G:BEL:EGY' => 14, 'H:KSA:URU' => 15,
        'G:IRN:NZL' => 16, 'I:FRA:SEN' => 17, 'I:IRQ:NOR' => 18, 'J:ARG:ALG' => 19, 'J:AUT:JOR' => 20,
        'K:POR:COD' => 21, 'L:ENG:CRO' => 22, 'L:GHA:PAN' => 23, 'K:UZB:COL' => 24, 'A:CZE:RSA' => 25,
        'B:SUI:BIH' => 26, 'B:CAN:QAT' => 27, 'A:MEX:KOR' => 28, 'D:USA:AUS' => 29, 'C:SCO:MAR' => 30,
        'C:BRA:HAI' => 31, 'D:TUR:PAR' => 32, 'F:NED:SWE' => 33, 'E:GER:CIV' => 34, 'E:ECU:CUW' => 35,
        'F:TUN:JPN' => 36, 'H:ESP:KSA' => 37, 'G:BEL:IRN' => 38, 'H:URU:CPV' => 39, 'G:NZL:EGY' => 40,
        'J:ARG:AUT' => 41, 'I:FRA:IRQ' => 42, 'I:NOR:SEN' => 43, 'J:JOR:ALG' => 44, 'K:POR:UZB' => 45,
        'L:ENG:GHA' => 46, 'L:PAN:CRO' => 47, 'K:COL:COD' => 48, 'B:SUI:CAN' => 49, 'B:BIH:QAT' => 50,
        'C:SCO:BRA' => 51, 'C:MAR:HAI' => 52, 'A:CZE:MEX' => 53, 'A:RSA:KOR' => 54, 'E:CUW:CIV' => 55,
        'E:ECU:GER' => 56, 'F:JPN:SWE' => 57, 'F:TUN:NED' => 58, 'D:TUR:USA' => 59, 'D:PAR:AUS' => 60,
        'I:NOR:FRA' => 61, 'I:SEN:IRQ' => 62, 'H:CPV:KSA' => 63, 'H:URU:ESP' => 64, 'G:EGY:IRN' => 65,
        'G:NZL:BEL' => 66, 'L:PAN:ENG' => 67, 'L:CRO:GHA' => 68, 'K:COL:POR' => 69, 'K:COD:UZB' => 70,
        'J:ALG:AUT' => 71, 'J:JOR:ARG' => 72,
    ];

    /**
     * The original group-by-group numbers, for {@see down()}.
     */
    private const LEGACY_NUMBERS = [
        'A:MEX:RSA' => 1, 'A:KOR:CZE' => 2, 'A:CZE:RSA' => 3, 'A:MEX:KOR' => 4, 'A:CZE:MEX' => 5,
        'A:RSA:KOR' => 6, 'B:CAN:BIH' => 7, 'B:QAT:SUI' => 8, 'B:SUI:BIH' => 9, 'B:CAN:QAT' => 10,
        'B:SUI:CAN' => 11, 'B:BIH:QAT' => 12, 'C:BRA:MAR' => 13, 'C:HAI:SCO' => 14, 'C:SCO:MAR' => 15,
        'C:BRA:HAI' => 16, 'C:SCO:BRA' => 17, 'C:MAR:HAI' => 18, 'D:USA:PAR' => 19, 'D:AUS:TUR' => 20,
        'D:USA:AUS' => 21, 'D:TUR:PAR' => 22, 'D:TUR:USA' => 23, 'D:PAR:AUS' => 24, 'E:GER:CUW' => 25,
        'E:CIV:ECU' => 26, 'E:GER:CIV' => 27, 'E:ECU:CUW' => 28, 'E:ECU:GER' => 29, 'E:CUW:CIV' => 30,
        'F:NED:JPN' => 31, 'F:SWE:TUN' => 32, 'F:NED:SWE' => 33, 'F:TUN:JPN' => 34, 'F:TUN:NED' => 35,
        'F:JPN:SWE' => 36, 'G:BEL:EGY' => 37, 'G:IRN:NZL' => 38, 'G:BEL:IRN' => 39, 'G:NZL:EGY' => 40,
        'G:NZL:BEL' => 41, 'G:EGY:IRN' => 42, 'H:ESP:CPV' => 43, 'H:KSA:URU' => 44, 'H:ESP:KSA' => 45,
        'H:URU:CPV' => 46, 'H:URU:ESP' => 47, 'H:CPV:KSA' => 48, 'I:FRA:SEN' => 49, 'I:IRQ:NOR' => 50,
        'I:FRA:IRQ' => 51, 'I:NOR:SEN' => 52, 'I:NOR:FRA' => 53, 'I:SEN:IRQ' => 54, 'J:ARG:ALG' => 55,
        'J:AUT:JOR' => 56, 'J:ARG:AUT' => 57, 'J:JOR:ALG' => 58, 'J:JOR:ARG' => 59, 'J:ALG:AUT' => 60,
        'K:POR:COD' => 61, 'K:UZB:COL' => 62, 'K:POR:UZB' => 63, 'K:COL:COD' => 64, 'K:COL:POR' => 65,
        'K:COD:UZB' => 66, 'L:ENG:CRO' => 67, 'L:GHA:PAN' => 68, 'L:ENG:GHA' => 69, 'L:PAN:CRO' => 70,
        'L:PAN:ENG' => 71, 'L:CRO:GHA' => 72,
    ];

    public function up(): void
    {
        $this->renumber(self::FIFA_NUMBERS);
    }

    public function down(): void
    {
        $this->renumber(self::LEGACY_NUMBERS);
    }

    /**
     * Apply the given "group:home:away" => match-number map to the World Cup 2026 group fixtures.
     *
     * @param  array<string, int>  $map
     */
    private function renumber(array $map): void
    {
        $tournamentId = DB::table('tournaments')->where('slug', 'world-cup-2026')->value('id');

        if ($tournamentId === null) {
            return;
        }

        DB::transaction(function () use ($tournamentId, $map): void {
            // Pass 1: lift the whole group block clear of the 1–72 range (knockout is 73–104) so the
            // unique (tournament_id, match_number) index can't collide while we reassign.
            DB::table('fixtures')
                ->where('tournament_id', $tournamentId)
                ->whereNotNull('group_id')
                ->update(['match_number' => DB::raw('match_number + 1000')]);

            // Pass 2: give each group fixture its target number by its stable (group, home, away)
            // identity — independent of its current number, so a re-run lands the same result.
            $fixtures = DB::table('fixtures')
                ->join('groups', 'fixtures.group_id', '=', 'groups.id')
                ->join('teams as home_team', 'fixtures.home_team_id', '=', 'home_team.id')
                ->join('teams as away_team', 'fixtures.away_team_id', '=', 'away_team.id')
                ->where('fixtures.tournament_id', $tournamentId)
                ->whereNotNull('fixtures.group_id')
                ->select('fixtures.id', 'groups.name as group', 'home_team.code as home', 'away_team.code as away')
                ->get();

            foreach ($fixtures as $fixture) {
                $number = $map[$fixture->group.':'.$fixture->home.':'.$fixture->away] ?? null;

                if ($number !== null) {
                    DB::table('fixtures')->where('id', $fixture->id)->update(['match_number' => $number]);
                }
            }
        });
    }
};
