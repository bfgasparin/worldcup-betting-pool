<?php

namespace App\Notifications;

use App\Enums\GameAccent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the player who newly reaches rank #1 on a game's leaderboard. Leads with the game's
 * source and accent so a player can tell which pool the email is about (games over the same
 * tournament share a name).
 */
class TopOfLeaderboardNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $gameName,
        public string $gameSlug,
        public string $source,
        public GameAccent $accent,
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
            ->subject(__("🏆 You're top of :source's :game", ['source' => $this->source, 'game' => $this->gameName]))
            ->view(['emails.top-of-leaderboard', 'emails.top-of-leaderboard-text'], [
                'gameName' => $this->gameName,
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
                'url' => route('games.leaderboard', $this->gameSlug),
            ]);
    }
}
