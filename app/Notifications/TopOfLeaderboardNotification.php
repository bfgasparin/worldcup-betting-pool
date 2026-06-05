<?php

namespace App\Notifications;

use App\Enums\PoolAccent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the player who newly reaches rank #1 on a pool's leaderboard. Leads with the pool's
 * source and accent so a player can tell which pool the email is about (pools over the same
 * tournament share a name).
 */
class TopOfLeaderboardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $poolName,
        public string $poolSlug,
        public string $source,
        public PoolAccent $accent,
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
            ->subject(__("🏆 You're top of :source's :pool", ['source' => $this->source, 'pool' => $this->poolName]))
            ->view(['emails.top-of-leaderboard', 'emails.top-of-leaderboard-text'], [
                'poolName' => $this->poolName,
                'source' => $this->source,
                'accentGradient' => $this->accent->gradientCss(),
                'accentSolid' => $this->accent->solidHex(),
                'accentInk' => $this->accent->eyebrowInk(),
                'leaderboardLabel' => $this->leaderboardLabel,
                'points' => $this->points,
                'totalEntries' => $this->totalEntries,
                'runnerUpName' => $this->runnerUpName,
                'leadOverRunnerUp' => $this->leadOverRunnerUp,
                'userName' => $notifiable->name,
                'url' => route('pools.leaderboard', $this->poolSlug),
            ]);
    }
}
