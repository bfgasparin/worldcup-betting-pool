<?php

namespace App\Notifications;

use App\Models\Pool;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to each administrator when a player newly joins a pool, so they can arrange the buy-in
 * payment with that player. Buy-in is collected externally, so this carries everything the
 * organizer needs to chase it: the joiner's contact details and the pool's price.
 */
class PlayerJoinedPoolNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Pool $pool, public User $player) {}

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
            ->subject(__('💰 :name joined :pool — arrange buy-in', [
                'name' => $this->player->name,
                'pool' => $this->pool->name,
            ]))
            ->view(['emails.player-joined-pool', 'emails.player-joined-pool-text'], [
                'playerName' => $this->player->name,
                'playerEmail' => $this->player->email,
                'poolName' => $this->pool->name,
                'source' => $this->pool->source,
                'entryPrice' => $this->pool->entry_price,
                'currency' => $this->pool->currency,
                'accentSolid' => $this->pool->accent->solidHex(),
                'accentGradient' => $this->pool->accent->gradientCss(),
                'accentInk' => $this->pool->accent->eyebrowInk(),
                'url' => route('pools.show', $this->pool->slug),
            ]);
    }
}
