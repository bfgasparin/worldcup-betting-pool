<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the player who newly reaches rank #1 on a tournament leaderboard.
 */
class TopOfLeaderboardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tournamentName,
        public string $tournamentSlug,
        public int $points,
        public int $totalEntries,
        public ?string $runnerUpName = null,
        public ?int $leadOverRunnerUp = null,
        public string $leaderboardLabel = 'Overall',
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__("🏆 You're top of :tournament!", ['tournament' => $this->tournamentName]))
            ->view(['emails.top-of-leaderboard', 'emails.top-of-leaderboard-text'], [
                'tournamentName' => $this->tournamentName,
                'leaderboardLabel' => $this->leaderboardLabel,
                'points' => $this->points,
                'totalEntries' => $this->totalEntries,
                'runnerUpName' => $this->runnerUpName,
                'leadOverRunnerUp' => $this->leadOverRunnerUp,
                'userName' => $notifiable->name,
                'url' => route('games.leaderboard', $this->tournamentSlug),
            ]);
    }
}
