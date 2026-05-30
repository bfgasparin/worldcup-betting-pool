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
     */
    public function toMail(object $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__(':app login code', ['app' => $appName]))
            ->greeting(__('Your login code'))
            ->line(__('Use the following code to finish signing in. It expires in :minutes minutes.', [
                'minutes' => SendLoginCode::TTL_MINUTES,
            ]))
            ->line($this->code)
            ->line(__('If you did not request this code, you can safely ignore this email.'));
    }
}
