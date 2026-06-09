<?php

namespace App\Notifications;

use App\Enums\PhaseKey;
use App\Enums\PoolAccent;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Lang;

/**
 * Sent to every player in a phased-bracket pool the moment a new knockout round's prediction window
 * opens (its real participants have just been decided). Leads with the pool's name and accent — the
 * source is secondary context — and carries the round's prediction deadline so a player knows how
 * long they have to get their picks in.
 */
class PredictionWindowOpenedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $poolName,
        public string $poolSlug,
        public string $source,
        public PoolAccent $accent,
        public PhaseKey $phaseKey,
        public string $roundNameEn,
        public ?CarbonInterface $deadline = null,
    ) {}

    /**
     * The localized round name, resolved at render time so it honors the recipient's preferred
     * locale (set for the send via {@see User::preferredLocale()}); falls back to the
     * canonical English phase name when the active locale has no translation.
     */
    private function roundName(): string
    {
        $key = 'phases.'.$this->phaseKey->value;

        return Lang::has($key) ? __($key) : $this->roundNameEn;
    }

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
        $roundName = $this->roundName();

        return (new MailMessage)
            ->subject(__('🎯 :round predictions are open in :pool', [
                'round' => $roundName,
                'pool' => $this->poolName,
            ]))
            ->view(['emails.window-opened', 'emails.window-opened-text'], [
                'poolName' => $this->poolName,
                'source' => $this->source,
                'accentGradient' => $this->accent->gradientCss(),
                'accentSolid' => $this->accent->solidHex(),
                'accentInk' => $this->accent->eyebrowInk(),
                'roundName' => $roundName,
                'deadlineLabel' => $deadline?->isoFormat('ddd D MMM YYYY, HH:mm'),
                'deadlineZone' => $deadline?->format('T'),
                'userName' => $notifiable->name,
                'url' => route('pools.predict.edit', $this->poolSlug),
            ]);
    }
}
