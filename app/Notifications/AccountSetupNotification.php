<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountSetupNotification extends Notification
{
    use Queueable;

    public function __construct(
        private readonly string $setupToken,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Set up your account')
            ->greeting('Welcome')
            ->line('You have been invited to set up your account.')
            ->line('Use the button below to choose your password and sign in.')
            ->action('Set Up Account', $this->setupUrl($notifiable))
            ->line('If you did not expect this email, you can ignore it.');
    }

    public function setupUrl(object $notifiable): string
    {
        return route('user.account-setup', [
            'accountSetupUuid' => $notifiable->account_setup_uuid,
            'token' => $this->setupToken,
        ]);
    }
}
