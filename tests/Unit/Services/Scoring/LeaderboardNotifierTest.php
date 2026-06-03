<?php

namespace Tests\Unit\Services\Scoring;

use App\Models\Entry;
use App\Models\Game;
use App\Models\User;
use App\Notifications\LeaderboardRankChangedNotification;
use App\Notifications\TopOfLeaderboardNotification;
use App\Services\Scoring\LeaderboardNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class LeaderboardNotifierTest extends TestCase
{
    use RefreshDatabase;

    private Game $game;

    private LeaderboardNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();

        $this->game = Game::factory()->create();
        $this->notifier = new LeaderboardNotifier;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function entryFor(User $user, array $attributes): Entry
    {
        return Entry::factory()->for($this->game)->for($user)->create($attributes);
    }

    public function test_a_player_who_newly_reaches_first_gets_the_milestone_and_no_rank_change_email(): void
    {
        Notification::fake();

        $ada = User::factory()->create(['name' => 'Ada']);
        $bo = User::factory()->create(['name' => 'Bo']);

        $this->entryFor($ada, ['total_points' => 200, 'rank' => 1, 'previous_rank' => 2]);
        $this->entryFor($bo, ['total_points' => 150, 'rank' => 2, 'previous_rank' => 1]);

        $this->notifier->notify($this->game);

        Notification::assertSentTo($ada, TopOfLeaderboardNotification::class, function ($notification): bool {
            return $notification->runnerUpName === 'Bo'
                && $notification->leadOverRunnerUp === 50
                && $notification->points === 200
                && $notification->totalEntries === 2
                && $notification->gameSlug === $this->game->slug
                // The game's identity rides along so the email leads with the right pool.
                && $notification->source === $this->game->source
                && $notification->accent === $this->game->accent;
        });
        Notification::assertNotSentTo($ada, LeaderboardRankChangedNotification::class);
        // Bo only dropped one place, which is below the threshold.
        Notification::assertNotSentTo($bo, LeaderboardRankChangedNotification::class);
    }

    public function test_a_significant_climb_sends_an_up_email_with_the_player_ahead(): void
    {
        Notification::fake();

        $ada = User::factory()->create(['name' => 'Ada']);
        $bo = User::factory()->create(['name' => 'Bo']);
        $cy = User::factory()->create(['name' => 'Cy']);
        $di = User::factory()->create(['name' => 'Di']);

        $this->entryFor($ada, ['total_points' => 150, 'rank' => 1, 'previous_rank' => 1]);
        $this->entryFor($bo, ['total_points' => 90, 'rank' => 2, 'previous_rank' => 4]); // up 2
        $this->entryFor($cy, ['total_points' => 70, 'rank' => 3, 'previous_rank' => 2]); // down 1
        $this->entryFor($di, ['total_points' => 50, 'rank' => 4, 'previous_rank' => 3]); // down 1

        $this->notifier->notify($this->game);

        Notification::assertSentTo($bo, LeaderboardRankChangedNotification::class, function ($notification): bool {
            return $notification->direction === 'up'
                && $notification->rank === 2
                && $notification->previousRank === 4
                && $notification->aheadName === 'Ada'
                && $notification->pointsBehind === 60
                && $notification->totalEntries === 4
                && $notification->source === $this->game->source
                && $notification->accent === $this->game->accent;
        });
        Notification::assertNotSentTo($cy, LeaderboardRankChangedNotification::class);
        Notification::assertNotSentTo($di, LeaderboardRankChangedNotification::class);
    }

    public function test_a_significant_drop_sends_a_down_email_with_the_player_ahead(): void
    {
        Notification::fake();

        $ada = User::factory()->create(['name' => 'Ada']);
        $bo = User::factory()->create(['name' => 'Bo']);
        $cy = User::factory()->create(['name' => 'Cy']);
        $di = User::factory()->create(['name' => 'Di']);

        $this->entryFor($ada, ['total_points' => 200, 'rank' => 1, 'previous_rank' => 1]); // stays
        $this->entryFor($bo, ['total_points' => 150, 'rank' => 2, 'previous_rank' => 3]);  // up 1
        $this->entryFor($cy, ['total_points' => 100, 'rank' => 3, 'previous_rank' => 4]);  // up 1
        $this->entryFor($di, ['total_points' => 40, 'rank' => 4, 'previous_rank' => 2]);   // down 2

        $this->notifier->notify($this->game);

        Notification::assertSentTo($di, LeaderboardRankChangedNotification::class, function ($notification): bool {
            return $notification->direction === 'down'
                && $notification->rank === 4
                && $notification->previousRank === 2
                && $notification->aheadName === 'Cy'
                && $notification->pointsBehind === 60
                && $notification->source === $this->game->source
                && $notification->accent === $this->game->accent;
        });
        Notification::assertNotSentTo($ada, TopOfLeaderboardNotification::class);
        Notification::assertNotSentTo($bo, LeaderboardRankChangedNotification::class);
        Notification::assertNotSentTo($cy, LeaderboardRankChangedNotification::class);
    }

    public function test_a_move_of_a_single_place_sends_nothing(): void
    {
        Notification::fake();

        $ada = User::factory()->create();
        $bo = User::factory()->create();
        $cy = User::factory()->create();

        $this->entryFor($ada, ['total_points' => 200, 'rank' => 1, 'previous_rank' => 1]); // stays #1
        $this->entryFor($bo, ['total_points' => 150, 'rank' => 2, 'previous_rank' => 3]);  // up 1
        $this->entryFor($cy, ['total_points' => 100, 'rank' => 3, 'previous_rank' => 2]);  // down 1

        $this->notifier->notify($this->game);

        Notification::assertNothingSent();
    }

    public function test_staying_top_sends_nothing(): void
    {
        Notification::fake();

        $ada = User::factory()->create();
        $bo = User::factory()->create();

        $this->entryFor($ada, ['total_points' => 200, 'rank' => 1, 'previous_rank' => 1]);
        $this->entryFor($bo, ['total_points' => 150, 'rank' => 2, 'previous_rank' => 2]);

        $this->notifier->notify($this->game);

        Notification::assertNothingSent();
    }

    public function test_unscored_entries_are_skipped(): void
    {
        Notification::fake();

        $ada = User::factory()->create();

        // Ranked first but without any points yet — no real milestone to celebrate.
        $this->entryFor($ada, ['total_points' => null, 'rank' => 1, 'previous_rank' => 2]);

        $this->notifier->notify($this->game);

        Notification::assertNothingSent();
    }

    public function test_the_first_ever_leader_gets_a_milestone(): void
    {
        Notification::fake();

        $ada = User::factory()->create(['name' => 'Ada']);
        $bo = User::factory()->create(['name' => 'Bo']);

        // First snapshot ever: previous_rank is null for everyone.
        $this->entryFor($ada, ['total_points' => 120, 'rank' => 1, 'previous_rank' => null]);
        $this->entryFor($bo, ['total_points' => 80, 'rank' => 2, 'previous_rank' => null]);

        $this->notifier->notify($this->game);

        Notification::assertSentTo($ada, TopOfLeaderboardNotification::class);
        // No prior position, so no climb/drop emails on the first scoring.
        Notification::assertNotSentTo($bo, LeaderboardRankChangedNotification::class);
    }
}
