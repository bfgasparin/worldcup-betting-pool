<?php

namespace App\Notifications;

use App\Models\Pool;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to a player when an admin overwrites their existing predictions through the backfill tool, so
 * they know an organizer replaced picks they had already made. Leads with the pool's name and accent
 * so the player can tell which pool it is about; the source is the secondary "by :source" context.
 */
class PredictionsOverwrittenNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Pool $pool) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Your predictions in :pool were updated', ['pool' => $this->pool->name]))
            ->view(['emails.predictions-overwritten', 'emails.predictions-overwritten-text'], [
                'poolName' => $this->pool->name,
                'source' => $this->pool->source,
                'accentGradient' => $this->pool->accent->gradientCss(),
                'accentSolid' => $this->pool->accent->solidHex(),
                'accentInk' => $this->pool->accent->eyebrowInk(),
                'userName' => $notifiable->name,
                'url' => route('pools.predict.edit', $this->pool->slug),
            ]);
    }
}
