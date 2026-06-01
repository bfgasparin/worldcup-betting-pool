<?php

namespace App\Notifications;

use App\Actions\Auth\SendLoginCode;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LoginCodeNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $code) {}

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
     *
     * Renders the branded FF&A email shell (HTML + plain text). The code is
     * deliberately kept out of the subject so it is not exposed in inbox or
     * lock-screen previews.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__(':app sign-in code', ['app' => config('app.name')]))
            ->view(['emails.login-code', 'emails.login-code-text'], [
                'code' => $this->code,
                'expiresInMinutes' => SendLoginCode::TTL_MINUTES,
                'email' => $notifiable->routeNotificationFor('mail') ?: $notifiable->email,
            ]);
    }
}
