<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PasswordResetCodeNotification extends Notification
{
    use Queueable;

    public function __construct(
        private string $code
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('auth.password_reset_code_subject'))
            ->line(__('auth.password_reset_code_line'))
            ->line("**{$this->code}**")
            ->line(__('auth.password_reset_code_expires', ['minutes' => 15]));
    }
}