<?php

namespace Tests\Feature;

use App\Enums\LeaderboardCategory;
use App\Models\Entry;
use App\Models\Game;
use App\Models\Group;
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

/**
 * The "compare players" payload on the game detail page. The load-bearing concern is the anti-cheat
 * gate: another player's predictions are revealed only once their prediction window has locked. Lock
 * state is set explicitly via {@see Game::$predictions_lock_at} (past = locked, future = open) so the
 * tests never depend on the wall clock relative to the seeded 2026 kickoffs.
 */
class GameCompareTest extends TestCase
{
    use RefreshDatabase;

    private Tournament $tournament;

    private Game $game;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(WorldCup2026Seeder::class);
        $this->tournament = Tournament::firstOrFail();
        $this->game = $this->tournament->games()->where('slug', 'world-cup-2026-ffa')->firstOrFail();
    }

    private function lockNow(): void
    {
        $this->game->update(['predictions_lock_at' => now()->subDay()]);
    }

    private function keepOpen(): void
    {
        $this->game->update(['predictions_lock_at' => now()->addDay()]);
    }

    private function entryFor(string $name): Entry
    {
        return Entry::factory()->for($this->game)->for(User::factory()->create(['name' => $name]))->create();
    }

    private function groupA(): Group
    {
        return $this->tournament->groups()->where('name', 'A')->firstOrFail();
    }

    public function test_no_comparison_in_normal_mode(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();

        $this->actingAs($user)
            ->get(route('games.show', 'world-cup-2026-ffa'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison', null)
                // Normal-mode props are untouched.
                ->has('groups', 12)
                ->has('bracket', 6)
                ->has('pool')
                ->has('players', 1)
            );
    }

    public function test_an_empty_or_garbage_compare_param_yields_no_comparison(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();

        $this->actingAs($user);

        foreach (['', 'abc', '0', ' , '] as $value) {
            $this->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => $value]))
                ->assertOk()
                ->assertInertia(fn (AssertableInertia $page) => $page->where('comparison', null));
        }
    }

    public function test_comparison_lists_the_viewer_first_then_opponents_in_order(): void
    {
        $user = User::factory()->create(['name' => 'Me']);
        Entry::factory()->for($this->game)->for($user)->create();
        $bruno = $this->entryFor('Bruno');
        $ana = $this->entryFor('Ana');

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => "{$ana->id},{$bruno->id}"]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('comparison.players', 3)
                ->where('comparison.players.0.is_viewer', true)
                ->where('comparison.players.0.name', 'You')
                // Requested order is preserved: Ana before Bruno.
                ->where('comparison.players.1.is_viewer', false)
                ->where('comparison.players.1.entry_id', $ana->id)
                ->where('comparison.players.1.name', 'Ana')
                ->where('comparison.players.2.entry_id', $bruno->id)
                ->where('comparison.players.2.name', 'Bruno')
            );
    }

    public function test_an_opponents_group_pick_is_hidden_before_the_window_locks(): void
    {
        $this->keepOpen();

        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');
        $fixture = $this->groupA()->fixtures()->orderBy('match_number')->first();

        GroupPrediction::factory()->create([
            'entry_id' => $opponent->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 3,
            'away_goals' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.windows.group', 'open')
                // The opponent's scoreline and their projected table are both withheld.
                ->missing("comparison.players.1.group_predictions.{$fixture->id}")
                ->where('comparison.players.1.projected_standings.A', null)
            );
    }

    public function test_an_opponents_group_pick_is_revealed_after_the_window_locks(): void
    {
        $this->lockNow();

        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');
        $fixture = $this->groupA()->fixtures()->orderBy('match_number')->first();

        GroupPrediction::factory()->create([
            'entry_id' => $opponent->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 3,
            'away_goals' => 1,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.windows.group', 'locked')
                ->where("comparison.players.1.group_predictions.{$fixture->id}.home_goals", 3)
                ->where("comparison.players.1.group_predictions.{$fixture->id}.away_goals", 1)
                // Their projected table now resolves (4 teams in the group).
                ->has('comparison.players.1.projected_standings.A', 4)
            );
    }

    public function test_an_opponents_knockout_pick_is_gated_by_its_window(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');

        $fixture = $this->tournament->knockoutFixtures()->orderBy('match_number')->first();
        [$home, $away] = Team::query()->take(2)->get()->all();

        KnockoutPrediction::create([
            'entry_id' => $opponent->id,
            'fixture_id' => $fixture->id,
            'predicted_home_team_id' => $home->id,
            'predicted_away_team_id' => $away->id,
            'home_goals' => 2,
            'away_goals' => 1,
            'advancing_team_id' => $home->id,
        ]);

        $this->actingAs($user);

        // Open: the knockout pick is withheld.
        $this->keepOpen();
        $this->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->missing("comparison.players.1.knockout_predictions.{$fixture->id}")
            );

        // Locked: now visible, with the upfront-bracket predicted teams exposed.
        $this->lockNow();
        $this->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where("comparison.players.1.knockout_predictions.{$fixture->id}.advancing_team_id", $home->id)
                ->where("comparison.players.1.knockout_predictions.{$fixture->id}.predicted_home.id", $home->id)
                ->where("comparison.players.1.knockout_predictions.{$fixture->id}.predicted_away.id", $away->id)
            );
    }

    public function test_the_viewers_own_picks_are_always_visible_even_before_lock(): void
    {
        $this->keepOpen();

        $user = User::factory()->create();
        $entry = Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');
        $fixture = $this->groupA()->fixtures()->orderBy('match_number')->first();

        GroupPrediction::factory()->create([
            'entry_id' => $entry->id,
            'fixture_id' => $fixture->id,
            'home_goals' => 2,
            'away_goals' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.windows.group', 'open')
                ->where("comparison.players.0.group_predictions.{$fixture->id}.home_goals", 2)
                ->where("comparison.players.0.group_predictions.{$fixture->id}.away_goals", 0)
                ->has('comparison.players.0.projected_standings.A', 4)
            );
    }

    public function test_points_and_board_totals_are_always_visible_regardless_of_lock(): void
    {
        $this->keepOpen();

        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');
        $opponent->update(['total_points' => 50, 'rank' => 1]);
        LeaderboardStanding::factory()->for($opponent)->create([
            'category' => LeaderboardCategory::MatchWinners,
            'value' => 12,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('comparison.players.1.total_points', 50)
                ->where('comparison.players.1.rank', 1)
                ->where('comparison.players.1.boards.0.key', 'overall')
                ->where('comparison.players.1.boards.0.primary_value', 50)
                ->where('comparison.players.1.boards.1.key', 'match-winners')
                ->where('comparison.players.1.boards.1.primary_value', 12)
            );
    }

    public function test_the_comparison_is_capped_at_three_opponents(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();

        $ids = collect(range(1, 4))
            ->map(fn (int $n): int => $this->entryFor("Rival {$n}")->id)
            ->implode(',');

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => $ids]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Viewer + 3 opponents (the 4th is dropped).
                ->has('comparison.players', 4)
            );
    }

    public function test_the_viewers_own_entry_id_is_ignored_in_the_compare_list(): void
    {
        $user = User::factory()->create();
        $mine = Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => "{$mine->id},{$opponent->id}"]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('comparison.players', 2)
                ->where('comparison.players.0.is_viewer', true)
                ->where('comparison.players.1.entry_id', $opponent->id)
            );
    }

    public function test_foreign_and_nonexistent_entry_ids_are_dropped(): void
    {
        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');

        // An entry that belongs to the sibling game, plus an id that doesn't exist at all.
        $brothers = $this->tournament->games()->where('slug', 'world-cup-2026-brothers')->firstOrFail();
        $foreign = Entry::factory()->for($brothers)->create();

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => "{$opponent->id},{$foreign->id},99999999"]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('comparison.players', 2)
                ->where('comparison.players.1.entry_id', $opponent->id)
            );
    }

    public function test_only_predicted_fixtures_appear_when_locked(): void
    {
        $this->lockNow();

        $user = User::factory()->create();
        Entry::factory()->for($this->game)->for($user)->create();
        $opponent = $this->entryFor('Rival');

        $fixtures = $this->groupA()->fixtures()->orderBy('match_number')->get();
        $predicted = $fixtures->first();
        $unpredicted = $fixtures->get(1);

        GroupPrediction::factory()->create([
            'entry_id' => $opponent->id,
            'fixture_id' => $predicted->id,
            'home_goals' => 1,
            'away_goals' => 0,
        ]);

        $this->actingAs($user)
            ->get(route('games.show', ['game' => 'world-cup-2026-ffa', 'compare' => (string) $opponent->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                // Predicted fixture present; the un-predicted one is absent (distinct from "hidden").
                ->where("comparison.players.1.group_predictions.{$predicted->id}.home_goals", 1)
                ->missing("comparison.players.1.knockout_predictions.{$unpredicted->id}")
                ->missing("comparison.players.1.group_predictions.{$unpredicted->id}")
            );
    }

    public function test_the_player_directory_lists_every_entry_for_the_picker(): void
    {
        $user = User::factory()->create(['name' => 'Me']);
        Entry::factory()->for($this->game)->for($user)->create(['total_points' => 10]);
        Entry::factory()->for($this->game)->for(User::factory()->create(['name' => 'Top']))->create(['total_points' => 99]);

        $this->actingAs($user)
            ->get(route('games.show', 'world-cup-2026-ffa'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('players', 2)
                // Ranked by points: the top scorer leads, the viewer is flagged.
                ->where('players.0.name', 'Top')
                ->where('players.0.points', 99)
                ->where('players.1.name', 'You')
                ->where('players.1.is_me', true)
                ->whereNot('players.1.entry_id', null)
            );
    }
}
