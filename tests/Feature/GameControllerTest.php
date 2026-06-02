<?php

namespace Tests\Feature;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Fixture;
use App\Models\GroupPrediction;
use App\Models\KnockoutPrediction;
use App\Models\LeaderboardStanding;
use App\Models\Team;
use App\Models\Tournament;
use App\Models\User;
use Database\Seeders\WorldCup2026Seeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class GameControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_from_the_games_index(): void
    {
        $this->get(route('games.index'))->assertRedirect(route('login'));
    }

    public function test_the_timezone_cookie_is_shared_to_the_frontend(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->withUnencryptedCookie('timezone', 'America/Sao_Paulo')
            ->get(route('games.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('timezone', 'America/Sao_Paulo')
            );
    }

    public function test_authenticated_users_see_the_seeded_game_on_the_index(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/index')
                ->has('games', 1)
                ->where('games.0.slug', 'world-cup-2026')
            );
    }

    public function test_show_renders_groups_and_bracket(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/show')
                ->where('game.slug', 'world-cup-2026')
                ->has('groups', 12)
                ->has('groups.0.teams', 4)
                ->has('groups.0.fixtures', 6)
                ->has('bracket', 6)
                ->where('bracket.0.phase_key', 'round_of_32')
                ->has('bracket.0.fixtures', 16)
                ->where('bracket.0.fixtures.0.home', null)
                ->whereNot('bracket.0.fixtures.0.home_label', null)
            );
    }

    public function test_show_resolves_group_fixture_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('groups.0.fixtures.0.home', null)
                ->whereNot('groups.0.fixtures.0.away', null)
            );
    }

    public function test_show_exposes_knockout_kick_off_dates(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->whereNot('bracket.0.fixtures.0.kicks_off_at', null)
                ->whereNot('bracket.0.fixtures.0.venue', null)
                ->whereNot('bracket.0.fixtures.0.venue_timezone', null)
                ->whereNot('groups.0.fixtures.0.kicks_off_at', null)
                ->whereNot('groups.0.fixtures.0.venue', null)
                ->whereNot('groups.0.fixtures.0.venue_timezone', null)
            );
    }

    public function test_show_includes_no_prediction_without_an_entry(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.prediction', null)
            );
    }

    public function test_show_surfaces_the_viewers_group_prediction(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        $entry = Entry::factory()->for($tournament->games()->firstOrFail())->for($user)->create();
        $fixture = $tournament->fixtures()->where('match_number', 1)->firstOrFail();

        GroupPrediction::factory()->create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 2,
            'away_goals' => 1,
        ]);

        $this->actingAs($user);

        $this->get(route('games.show', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.fixtures.0.prediction.home_goals', 2)
                ->where('groups.0.fixtures.0.prediction.away_goals', 1)
                ->where('groups.0.fixtures.0.prediction.points_awarded', null)
            );
    }

    public function test_show_exposes_the_admin_review_flag_and_settled_card_fields(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $admin = User::factory()->create();
        config()->set('admin.emails', [$admin->email]);

        $this->actingAs($admin)
            ->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.can_review_scores', true)
                // The knockout fixtures expose the fields a settled card needs.
                ->where('bracket.0.fixtures.0.winner_team_id', null)
                ->where('bracket.0.fixtures.0.home_penalties', null)
                ->where('bracket.0.fixtures.0.prediction', null)
            );

        $this->actingAs(User::factory()->create())
            ->get(route('games.show', 'world-cup-2026'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.can_review_scores', false)
            );
    }

    public function test_show_exposes_the_viewers_predicted_knockout_teams(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        $entry = Entry::factory()->for($tournament->games()->firstOrFail())->for($user)->create();

        $fixture = $tournament->knockoutFixtures()->orderBy('match_number')->first();
        [$home, $away] = Team::query()->take(2)->get()->all();

        KnockoutPrediction::create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'predicted_home_team_id' => $home->id,
            'predicted_away_team_id' => $away->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $home->id,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bracket.0.fixtures.0.prediction.predicted_home.id', $home->id)
                ->where('bracket.0.fixtures.0.prediction.predicted_away.id', $away->id)
                ->whereNot('bracket.0.fixtures.0.prediction.predicted_home.flag_url', null)
            );
    }

    public function test_show_exposes_the_advancing_team_for_a_drawn_knockout_pick(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        $entry = Entry::factory()->for($tournament->games()->firstOrFail())->for($user)->create();

        $fixture = $tournament->knockoutFixtures()->orderBy('match_number')->first();
        [$home, $away] = Team::query()->take(2)->get()->all();

        // A drawn pick where the away team was chosen to advance (extra time / penalties).
        KnockoutPrediction::create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'predicted_home_team_id' => $home->id,
            'predicted_away_team_id' => $away->id,
            'home_goals' => 1,
            'away_goals' => 1,
            'advancing_team_id' => $away->id,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('bracket.0.fixtures.0.prediction.home_goals', 1)
                ->where('bracket.0.fixtures.0.prediction.away_goals', 1)
                ->where('bracket.0.fixtures.0.prediction.advancing_team_id', $away->id)
            );
    }

    public function test_leaderboard_exposes_rank_movement(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();

        // A climbed from 2nd to 1st; B slipped from 1st to 2nd.
        Entry::factory()->for($tournament->games()->firstOrFail())
            ->for(User::factory()->create(['name' => 'Climber']))
            ->create(['total_points' => 120, 'rank' => 1, 'previous_rank' => 2]);
        $me = User::factory()->create(['name' => 'Slider']);
        Entry::factory()->for($tournament->games()->firstOrFail())->for($me)
            ->create(['total_points' => 40, 'rank' => 2, 'previous_rank' => 1]);

        $this->actingAs($me)
            ->get(route('games.leaderboard', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boards.0.key', 'overall')
                ->where('boards.0.rows.0.movement', 'up')
                ->where('boards.0.rows.1.movement', 'down')
            );
    }

    public function test_show_includes_official_group_standings(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        // Before any match is played the table is seed-ordered with everyone on zero.
        $this->get(route('games.show', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('groups.0.standings', 4)
                ->where('groups.0.standings.0.rank', 1)
                ->where('groups.0.standings.0.played', 0)
                ->where('groups.0.standings.0.points', 0)
                ->whereNot('groups.0.standings.0.team', null)
                ->has('groups.0.standings.0.form', 0)
                ->where('groups.0.standings.3.rank', 4)
            );
    }

    public function test_group_standings_reflect_official_results(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $groupA = $tournament->groups()->where('name', 'A')->firstOrFail();

        $positions = $groupA->teams()->get()->mapWithKeys(
            fn ($team): array => [$team->id => (int) $team->pivot->position],
        );

        // Official results: the better-seeded team (lower group position) wins 1–0 every match.
        foreach ($groupA->fixtures()->get() as $fixture) {
            $homeWins = $positions[$fixture->home_team_id] < $positions[$fixture->away_team_id];

            $fixture->update([
                'home_goals' => $homeWins ? 1 : 0,
                'away_goals' => $homeWins ? 0 : 1,
            ]);
        }

        $topTeamId = $positions->search(1, true);

        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', $tournament->slug))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.name', 'A')
                ->where('groups.0.standings.0.rank', 1)
                ->where('groups.0.standings.0.team.id', $topTeamId)
                ->where('groups.0.standings.0.played', 3)
                ->where('groups.0.standings.0.won', 3)
                ->where('groups.0.standings.0.points', 9)
                ->where('groups.0.standings.3.points', 0)
            );
    }

    public function test_show_exposes_the_scoring_strategy_and_how_to_play(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('game.scoring_strategy', 'upfront-bracket')
                ->where('game.scoring_label', 'Upfront Bracket')
                ->whereNot('game.scoring_description', null)
                ->whereNot('game.how_to_play.summary', null)
                ->has('game.how_to_play.steps')
                ->whereNot('game.predictions_lock_at', null)
            );
    }

    public function test_show_has_no_predicted_standings_without_an_entry(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $this->actingAs(User::factory()->create());

        $this->get(route('games.show', 'world-cup-2026'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('groups.0.predicted_standings', null)
            );
    }

    public function test_show_includes_the_viewers_predicted_standings(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $groupA = $tournament->groups()->where('name', 'A')->firstOrFail();
        $user = User::factory()->create();
        $entry = Entry::factory()->for($tournament->games()->firstOrFail())->for($user)->create();

        $positions = $groupA->teams()->get()->mapWithKeys(
            fn ($team): array => [$team->id => (int) $team->pivot->position],
        );

        // The viewer predicts the better-seeded team to win 1–0 in every group A match.
        foreach ($groupA->fixtures()->get() as $fixture) {
            $homeWins = $positions[$fixture->home_team_id] < $positions[$fixture->away_team_id];

            GroupPrediction::factory()->create([
                'entry_id' => $entry->id,
                'fixture_id' => $fixture->id,
                'home_goals' => $homeWins ? 1 : 0,
                'away_goals' => $homeWins ? 0 : 1,
            ]);
        }

        $topTeamId = $positions->search(1, true);

        $this->actingAs($user);

        $this->get(route('games.show', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                // The official table is still all zeros (no real results yet)...
                ->where('groups.0.standings.0.played', 0)
                // ...while the predicted table reflects the viewer's own picks.
                ->has('groups.0.predicted_standings', 4)
                ->where('groups.0.predicted_standings.0.rank', 1)
                ->where('groups.0.predicted_standings.0.team.id', $topTeamId)
                ->where('groups.0.predicted_standings.0.played', 3)
                ->where('groups.0.predicted_standings.0.won', 3)
                ->where('groups.0.predicted_standings.0.points', 9)
                ->where('groups.0.predicted_standings.3.points', 0)
            );
    }

    public function test_the_final_is_seeded_with_its_kick_off_date(): void
    {
        $this->seed(WorldCup2026Seeder::class);

        $final = Fixture::where('match_number', 104)->firstOrFail();

        $this->assertNotNull($final->kicks_off_at);
        $this->assertSame('2026-07-19', $final->kicks_off_at->toDateString());
    }

    public function test_show_includes_the_pool_summary(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $user = User::factory()->create();
        Entry::factory()->for($tournament->games()->firstOrFail())->for($user)->create();

        $this->actingAs($user);

        $this->get(route('games.show', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('pool.participants', 1)
                ->where('pool.has_scores', false)
                ->where('pool.me.is_me', true)
                ->where('pool.me.points', null)
                ->has('pool.top', 1)
                // A summary per non-Overall board; before any scores the leader and the viewer's
                // position are both null.
                ->has('boardSummaries', 2)
                ->where('boardSummaries.0.key', 'match-winners')
                ->where('boardSummaries.0.leader', null)
                ->where('boardSummaries.0.you', null)
                // Board descriptors for the dialog (each carrying its tie-break stat).
                ->has('game.leaderboards', 3)
                ->where('game.leaderboards.0.key', 'overall')
                ->where('game.leaderboards.0.secondary_stat_label', null)
                ->where('game.leaderboards.1.key', 'match-winners')
                ->where('game.leaderboards.1.secondary_stat_label', 'Team goals')
            );
    }

    public function test_show_summarises_each_board_with_the_leader_and_your_position(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $game = $tournament->games()->firstOrFail();

        $leader = Entry::factory()->for($game)
            ->for(User::factory()->create(['name' => 'Caller']))
            ->create(['total_points' => 90]);
        $me = User::factory()->create(['name' => 'Me']);
        $mine = Entry::factory()->for($game)->for($me)->create(['total_points' => 40]);

        // Match Winners: Caller leads (12), I'm behind (3).
        LeaderboardStanding::factory()->for($leader)->create(['category' => LeaderboardCategory::MatchWinners, 'value' => 12]);
        LeaderboardStanding::factory()->for($mine)->create(['category' => LeaderboardCategory::MatchWinners, 'value' => 3]);

        $this->actingAs($me);

        $this->get(route('games.show', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boardSummaries.0.key', 'match-winners')
                ->where('boardSummaries.0.leader.name', 'Caller')
                ->where('boardSummaries.0.leader.primary_value', 12)
                ->where('boardSummaries.0.you.rank', 2)
                ->where('boardSummaries.0.you.primary_value', 3)
            );
    }

    public function test_guests_are_redirected_from_the_leaderboard(): void
    {
        $this->get(route('games.leaderboard', 'world-cup-2026'))
            ->assertRedirect(route('login'));
    }

    public function test_leaderboard_lists_pool_participants_without_scores(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        Entry::factory()->count(2)->for($tournament->games()->firstOrFail())->create();
        $me = User::factory()->create();
        Entry::factory()->for($tournament->games()->firstOrFail())->for($me)->create();

        $this->actingAs($me);

        $this->get(route('games.leaderboard', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('games/leaderboard')
                ->where('game.slug', $tournament->slug)
                ->has('boards', 3)
                ->where('boards.0.key', 'overall')
                ->where('boards.0.has_scores', false)
                ->has('boards.0.rows', 3)
            );
    }

    public function test_leaderboard_ranks_the_overall_board_by_total_points(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();

        Entry::factory()->for($tournament->games()->firstOrFail())
            ->for(User::factory()->create(['name' => 'Top Scorer']))
            ->create(['total_points' => 120]);

        $me = User::factory()->create(['name' => 'Runner Up']);
        Entry::factory()->for($tournament->games()->firstOrFail())->for($me)->create(['total_points' => 40]);

        $this->actingAs($me);

        $this->get(route('games.leaderboard', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boards.0.key', 'overall')
                ->where('boards.0.has_scores', true)
                ->where('boards.0.rows.0.rank', 1)
                ->where('boards.0.rows.0.name', 'Top Scorer')
                ->where('boards.0.rows.0.primary_value', 120)
                ->where('boards.0.rows.1.rank', 2)
                ->where('boards.0.rows.1.name', 'You')
                ->where('boards.0.rows.1.is_me', true)
            );
    }

    public function test_leaderboard_ranks_a_category_board_by_its_standings(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $game = $tournament->games()->firstOrFail();

        // The Overall leader trails on Match Winners, where a different player leads.
        $caller = Entry::factory()->for($game)
            ->for(User::factory()->create(['name' => 'Caller']))
            ->create(['total_points' => 50]);
        $me = User::factory()->create(['name' => 'Me']);
        $mine = Entry::factory()->for($game)->for($me)->create(['total_points' => 90]);

        LeaderboardStanding::factory()->for($caller)->create(['category' => LeaderboardCategory::Overall, 'value' => 50]);
        LeaderboardStanding::factory()->for($mine)->create(['category' => LeaderboardCategory::Overall, 'value' => 90]);
        LeaderboardStanding::factory()->for($caller)->create(['category' => LeaderboardCategory::MatchWinners, 'value' => 12, 'tiebreaker' => 30]);
        LeaderboardStanding::factory()->for($mine)->create(['category' => LeaderboardCategory::MatchWinners, 'value' => 3, 'tiebreaker' => 8]);

        $this->actingAs($me);

        $this->get(route('games.leaderboard', $tournament->slug))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('boards.1.key', 'match-winners')
                ->where('boards.1.rows.0.name', 'Caller')
                ->where('boards.1.rows.0.primary_value', 12)
                ->where('boards.1.rows.0.secondary_value', 30)
                ->where('boards.1.rows.1.name', 'You')
                ->where('boards.1.rows.1.is_me', true)
            );
    }

    public function test_leaderboard_preselects_a_requested_board(): void
    {
        $this->seed(WorldCup2026Seeder::class);
        $tournament = Tournament::firstOrFail();
        $this->actingAs(User::factory()->create());

        $this->get(route('games.leaderboard', ['game' => $tournament->slug, 'board' => 'match-winners']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active_board', 'match-winners')
            );

        // An unknown board falls back to no preselection (the page defaults to Overall).
        $this->get(route('games.leaderboard', ['game' => $tournament->slug, 'board' => 'nonsense']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('active_board', null)
            );
    }
}
