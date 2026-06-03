<?php

namespace App\Notifications;

use App\Enums\GameAccent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Sent when a player makes a significant move (up or down) on a game's leaderboard. Leads with the
 * game's source and accent so a player can tell which pool the email is about (games over the same
 * tournament share a name).
 */
class LeaderboardRankChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $gameName,
        public string $gameSlug,
        public string $source,
        public GameAccent $accent,
        public string $direction,
        public int $rank,
        public int $previousRank,
        public int $totalEntries,
        public int $points,
        public ?string $aheadName = null,
        public ?int $pointsBehind = null,
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
        $ordinal = Number::ordinal($this->rank);

        $subject = $this->direction === 'up'
            ? __("📈 You climbed to :rank in :source's :game", ['rank' => $ordinal, 'source' => $this->source, 'game' => $this->gameName])
            : __("You slipped to :rank in :source's :game", ['rank' => $ordinal, 'source' => $this->source, 'game' => $this->gameName]);

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.rank-change', 'emails.rank-change-text'], [
                'gameName' => $this->gameName,
                'source' => $this->source,
                'accentGradient' => $this->accent->gradientCss(),
                'accentSolid' => $this->accent->solidHex(),
                'accentInk' => $this->accent->eyebrowInk(),
                'leaderboardLabel' => $this->leaderboardLabel,
                'direction' => $this->direction,
                'rank' => $this->rank,
                'previousRank' => $this->previousRank,
                'delta' => abs($this->previousRank - $this->rank),
                'totalEntries' => $this->totalEntries,
                'points' => $this->points,
                'aheadName' => $this->aheadName,
                'pointsBehind' => $this->pointsBehind,
                'userName' => $notifiable->name,
                'url' => route('games.leaderboard', $this->gameSlug),
            ]);
    }
}
