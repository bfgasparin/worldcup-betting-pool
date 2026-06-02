<?php

namespace Tests\Feature;

use App\Enums\FeederOutcome;
use App\Enums\PhaseType;
use App\Enums\ScoringStrategy;
use App\Models\Fixture;
use App\Models\Game;
use App\Models\Group;
use App\Models\Team;
use App\Models\Tournament;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorldCup2026SeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_builds_the_full_world_cup_2026_structure(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();

        $this->assertSame('World Cup 2026', $tournament->name);
        $this->assertSame(7, $tournament->phases()->count());
        $this->assertSame(2, $tournament->games()->count());
        $this->assertSame(12, $tournament->groups()->count());
        $this->assertSame(48, Team::count());
        $this->assertSame(104, $tournament->fixtures()->count());
        $this->assertSame(72, $tournament->groupFixtures()->count());
        $this->assertSame(32, $tournament->knockoutFixtures()->count());
    }

    public function test_every_group_has_exactly_four_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $groups = Group::with('teams')->get();

        $this->assertCount(12, $groups);

        foreach ($groups as $group) {
            $this->assertCount(4, $group->teams, "Group {$group->name} should have 4 teams.");
        }
    }

    public function test_it_creates_the_expected_knockout_bracket_counts(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $this->assertSame(16, Fixture::where('bracket_slot', 'like', 'R32-%')->count());
        $this->assertSame(8, Fixture::where('bracket_slot', 'like', 'R16-%')->count());
        $this->assertSame(4, Fixture::where('bracket_slot', 'like', 'QF-%')->count());
        $this->assertSame(2, Fixture::where('bracket_slot', 'like', 'SF-%')->count());
        $this->assertSame(1, Fixture::where('bracket_slot', 'TP')->count());
        $this->assertSame(1, Fixture::where('bracket_slot', 'F')->count());
    }

    public function test_round_of_32_slots_are_unresolved_but_labelled(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $r32 = Fixture::where('bracket_slot', 'like', 'R32-%')->get();

        foreach ($r32 as $fixture) {
            $this->assertNull($fixture->home_team_id);
            $this->assertNull($fixture->away_team_id);
            $this->assertNull($fixture->home_feeder_fixture_id);
            $this->assertNotNull($fixture->home_placeholder_label);
            $this->assertNotNull($fixture->away_placeholder_label);
        }
    }

    public function test_later_knockout_rounds_have_resolvable_feeders(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $final = Fixture::where('bracket_slot', 'F')->firstOrFail();
        $this->assertSame('SF-1', $final->homeFeeder->bracket_slot);
        $this->assertSame('SF-2', $final->awayFeeder->bracket_slot);
        $this->assertSame(FeederOutcome::Winner, $final->home_feeder_outcome);

        $thirdPlace = Fixture::where('bracket_slot', 'TP')->firstOrFail();
        $this->assertSame(FeederOutcome::Loser, $thirdPlace->home_feeder_outcome);
        $this->assertSame(FeederOutcome::Loser, $thirdPlace->away_feeder_outcome);
        $this->assertSame(PhaseType::Knockout, $thirdPlace->phase->type);
    }

    public function test_it_seeds_the_completed_draw_with_real_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        // The 2026 draw is complete: every one of the 48 teams is a real qualifier.
        $this->assertSame(0, Team::where('is_placeholder', true)->count());

        $brazil = Team::where('code', 'BRA')->firstOrFail();
        $this->assertFalse($brazil->is_placeholder);

        // Hosts sit in their predetermined groups at seed position 1.
        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();
        $this->assertSame('MEX', $this->teamAt($tournament, 'A', 1)->code);
        $this->assertSame('CAN', $this->teamAt($tournament, 'B', 1)->code);
        $this->assertSame('USA', $this->teamAt($tournament, 'D', 1)->code);
    }

    public function test_round_of_32_slots_use_the_official_third_place_labels(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $thirdSlots = Fixture::where('bracket_slot', 'like', 'R32-%')
            ->where('away_placeholder_label', 'like', '3rd Group %')
            ->get();

        // Exactly eight Round-of-32 matches take a third-placed team.
        $this->assertCount(8, $thirdSlots);

        $match74 = Fixture::where('match_number', 74)->firstOrFail();
        $this->assertSame('Winner Group E', $match74->home_placeholder_label);
        $this->assertSame('3rd Group A/B/C/D/F', $match74->away_placeholder_label);
    }

    public function test_round_of_16_feeders_follow_the_official_bracket(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        // Match 89 (R16-1) is fed by the winners of matches 74 (R32-2) and 77 (R32-5).
        $match89 = Fixture::where('match_number', 89)->firstOrFail();
        $this->assertSame(74, $match89->homeFeeder->match_number);
        $this->assertSame(77, $match89->awayFeeder->match_number);
        $this->assertSame(FeederOutcome::Winner, $match89->home_feeder_outcome);

        // Match 90 (R16-2) is fed by the winners of matches 73 and 75.
        $match90 = Fixture::where('match_number', 90)->firstOrFail();
        $this->assertSame(73, $match90->homeFeeder->match_number);
        $this->assertSame(75, $match90->awayFeeder->match_number);
    }

    public function test_it_seeds_official_kick_off_times_and_venues(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        // Every fixture carries a venue + IANA timezone.
        $this->assertSame(0, Fixture::whereNull('venue')->count());
        $this->assertSame(0, Fixture::whereNull('venue_timezone')->count());
        $this->assertSame(0, Fixture::whereNull('kicks_off_at')->count());

        // The opener — Mexico v South Africa, 3 p.m. ET (19:00 UTC) at Mexico City.
        $opener = Fixture::where('match_number', 1)->firstOrFail();
        $this->assertSame('2026-06-11T19:00:00+00:00', $opener->kicks_off_at->toIso8601String());
        $this->assertSame('Mexico City Stadium', $opener->venue);
        $this->assertSame('America/Mexico_City', $opener->venue_timezone);
        $this->assertSame('MEX', $opener->homeTeam->code);
        $this->assertSame('RSA', $opener->awayTeam->code);

        // A late kick-off stays correct across timezones: 9 p.m. local on Jun 13 in Vancouver
        // is really 04:00 UTC on Jun 14 (Australia v Türkiye).
        $vancouver = Fixture::where('match_number', 20)->firstOrFail();
        $this->assertSame('2026-06-14T04:00:00+00:00', $vancouver->kicks_off_at->toIso8601String());
        $this->assertSame(
            '2026-06-13 21:00',
            $vancouver->kicks_off_at->copy()->setTimezone('America/Vancouver')->format('Y-m-d H:i'),
        );

        // Corrected group-stage kick-offs (previously off by hours/a day vs the official schedule).
        $this->assertSame(
            '2026-06-19T22:00:00+00:00',
            Fixture::where('match_number', 15)->firstOrFail()->kicks_off_at->toIso8601String(),
        );
        $this->assertSame(
            '2026-06-17T04:00:00+00:00',
            Fixture::where('match_number', 56)->firstOrFail()->kicks_off_at->toIso8601String(),
        );

        // Corrected knockout fixtures whose date/time/venue were permuted between same-day matches.
        $match74 = Fixture::where('match_number', 74)->firstOrFail();
        $this->assertSame('2026-06-29T20:30:00+00:00', $match74->kicks_off_at->toIso8601String());
        $this->assertSame('Boston Stadium', $match74->venue);
        $this->assertSame('America/New_York', $match74->venue_timezone);

        $match87 = Fixture::where('match_number', 87)->firstOrFail();
        $this->assertSame('2026-07-04T01:30:00+00:00', $match87->kicks_off_at->toIso8601String());
        $this->assertSame('Kansas City Stadium', $match87->venue);

        // Third-place play-off now uses the official 17:00 ET (21:00 UTC) kick-off.
        $this->assertSame(
            '2026-07-18T21:00:00+00:00',
            Fixture::where('match_number', 103)->firstOrFail()->kicks_off_at->toIso8601String(),
        );

        // The Final — 3 p.m. ET on Jul 19 (19:00 UTC) at MetLife / New York New Jersey.
        $final = Fixture::where('match_number', 104)->firstOrFail();
        $this->assertSame('2026-07-19T19:00:00+00:00', $final->kicks_off_at->toIso8601String());
        $this->assertSame('New York New Jersey Stadium', $final->venue);
        $this->assertSame('America/New_York', $final->venue_timezone);
    }

    public function test_it_corrects_home_away_order_to_match_fifa(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        // Group matches whose home/away order was flipped versus the official FIFA schedule.
        $match5 = Fixture::where('match_number', 5)->firstOrFail();
        $this->assertSame('CZE', $match5->homeTeam->code);
        $this->assertSame('MEX', $match5->awayTeam->code);

        $match32 = Fixture::where('match_number', 32)->firstOrFail();
        $this->assertSame('SWE', $match32->homeTeam->code);
        $this->assertSame('TUN', $match32->awayTeam->code);
    }

    private function teamAt(Tournament $tournament, string $group, int $position): Team
    {
        return $tournament->groups()->where('name', $group)->firstOrFail()
            ->teams()->wherePivot('position', $position)->firstOrFail();
    }

    public function test_it_seeds_the_ffa_game_over_the_competition(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();
        $game = Game::where('slug', 'world-cup-2026-ffa')->firstOrFail();

        $this->assertTrue($game->tournament->is($tournament));
        $this->assertSame('World Cup 2026', $game->name);
        $this->assertSame('FF&A', $game->source);
        $this->assertSame(ScoringStrategy::UpfrontBracket, $game->scoring_strategy);
        $this->assertSame(20, $game->scoring_config['group']['exact_score']);
    }

    public function test_it_seeds_the_brothers_association_phased_game(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $tournament = Tournament::where('slug', 'world-cup-2026')->firstOrFail();
        $game = Game::where('slug', 'world-cup-2026-brothers')->firstOrFail();

        $this->assertTrue($game->tournament->is($tournament));
        $this->assertSame('World Cup 2026', $game->name);
        $this->assertSame('Brothers Association', $game->source);
        $this->assertSame(ScoringStrategy::PhasedBracket, $game->scoring_strategy);

        // Rising-stakes knockout config: a flat advancing bonus plus per-round multipliers.
        $this->assertSame(10, $game->scoring_config['knockout']['advancing_team']);
        $this->assertSame(8, $game->scoring_config['knockout']['round_multipliers']['final']);
        $this->assertSame(1, $game->scoring_config['knockout']['round_multipliers']['round_of_32']);
    }

    public function test_it_is_idempotent(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->seed(WorldCup2026Seeder::class);

        $this->assertSame(1, Tournament::count());
        $this->assertSame(2, Game::count());
        $this->assertSame(48, Team::count());
        $this->assertSame(104, Fixture::count());
        $this->assertSame(48, \DB::table('group_team')->count());
    }
}
