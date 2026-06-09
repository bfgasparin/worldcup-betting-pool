<?php

namespace App\Notifications;

use App\Enums\LeaderboardCategory;
use App\Enums\PoolAccent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Number;

/**
 * Sent when a player makes a significant move (up or down) on a pool's leaderboard. Leads with the
 * pool's source and accent so a player can tell which pool the email is about (pools over the same
 * tournament share a name).
 */
class LeaderboardRankChangedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $poolName,
        public string $poolSlug,
        public string $source,
        public PoolAccent $accent,
        public string $direction,
        public int $rank,
        public int $previousRank,
        public int $totalEntries,
        public int $points,
        public ?string $aheadName = null,
        public ?int $pointsBehind = null,
        public LeaderboardCategory $category = LeaderboardCategory::Overall,
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
            ? __("📈 You climbed to :rank in :source's :pool", ['rank' => $ordinal, 'source' => $this->source, 'pool' => $this->poolName])
            : __("You slipped to :rank in :source's :pool", ['rank' => $ordinal, 'source' => $this->source, 'pool' => $this->poolName]);

        return (new MailMessage)
            ->subject($subject)
            ->view(['emails.rank-change', 'emails.rank-change-text'], [
                'poolName' => $this->poolName,
                'source' => $this->source,
                'accentGradient' => $this->accent->gradientCss(),
                'accentSolid' => $this->accent->solidHex(),
                'accentInk' => $this->accent->eyebrowInk(),
                // Resolved here (inside toMail) so it honors the recipient's preferred locale,
                // which Laravel sets for the send via User::preferredLocale().
                'leaderboardLabel' => $this->category->label(),
                'direction' => $this->direction,
                'rank' => $this->rank,
                'previousRank' => $this->previousRank,
                'delta' => abs($this->previousRank - $this->rank),
                'totalEntries' => $this->totalEntries,
                'points' => $this->points,
                'aheadName' => $this->aheadName,
                'pointsBehind' => $this->pointsBehind,
                'userName' => $notifiable->name,
                'url' => route('pools.leaderboard', $this->poolSlug),
            ]);
    }
}
