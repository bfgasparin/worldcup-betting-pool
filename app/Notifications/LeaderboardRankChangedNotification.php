<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Sent when a player makes a significant move (up or down) on a tournament leaderboard.
 */
class LeaderboardRankChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $tournamentName,
        public string $tournamentSlug,
        public string $direction,
        public int $rank,
        public int $previousRank,
        public int $totalEntries,
        public int $points,
        public ?string $aheadName = null,
        public ?int $pointsBehind = null,
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
            ? __('📈 You climbed to :rank in :tournament', ['rank' => $ordinal, 'tournament' => $this->tournamentName])
            : __('You slipped to :rank in :tournament', ['rank' => $ordinal, 'tournament' => $this->tournamentName]);

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.rank-change', 'emails.rank-change-text'], [
                'tournamentName' => $this->tournamentName,
                'direction' => $this->direction,
                'rank' => $this->rank,
                'previousRank' => $this->previousRank,
                'delta' => abs($this->previousRank - $this->rank),
                'totalEntries' => $this->totalEntries,
                'points' => $this->points,
                'aheadName' => $this->aheadName,
                'pointsBehind' => $this->pointsBehind,
                'userName' => $notifiable->name,
                'url' => route('games.leaderboard', $this->tournamentSlug),
            ]);
    }
}
