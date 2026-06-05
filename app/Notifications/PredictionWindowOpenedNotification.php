<?php

namespace App\Notifications;

use App\Enums\PoolAccent;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to every player in a phased-bracket pool the moment a new knockout round's prediction window
 * opens (its real participants have just been decided). Leads with the pool's source and accent —
 * pools over the same tournament share a name — and carries the round's prediction deadline so a
 * player knows how long they have to get their picks in.
 */
class PredictionWindowOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $poolName,
        public string $poolSlug,
        public string $source,
        public PoolAccent $accent,
        public string $roundName,
        public ?CarbonInterface $deadline = null,
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
        $deadline = $this->deadline?->timezone(config('app.timezone'));

        return (new MailMessage)
            ->subject(__("🎯 :round predictions are open in :source's :pool", [
                'round' => $this->roundName,
                'source' => $this->source,
                'pool' => $this->poolName,
            ]))
            ->view(['emails.window-opened', 'emails.window-opened-text'], [
                'poolName' => $this->poolName,
                'source' => $this->source,
                'accentGradient' => $this->accent->gradientCss(),
                'accentSolid' => $this->accent->solidHex(),
                'accentInk' => $this->accent->eyebrowInk(),
                'roundName' => $this->roundName,
                'deadlineLabel' => $deadline?->isoFormat('ddd D MMM YYYY, HH:mm'),
                'deadlineZone' => $deadline?->format('T'),
                'userName' => $notifiable->name,
                'url' => route('pools.predict.edit', $this->poolSlug),
            ]);
    }
}
