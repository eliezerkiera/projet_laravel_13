<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailVerificationCodeNotification extends Notification
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
    $verificationUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        [
            'id'   => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]
    );

    return (new MailMessage)
        ->subject(__('auth.verification_code_subject'))
        ->line(__('auth.verification_code_line'))
        ->line("**{$this->code}**")
        ->line(__('auth.verification_code_expires', ['minutes' => 15]))
        ->line(__('auth.or_click_the_link'))
        ->action(__('auth.verify_email_button'), $verificationUrl);
}
}