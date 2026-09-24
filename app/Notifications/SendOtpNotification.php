<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SendOtpNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @param  string  $code  The 6-digit verification code
     * @param  string  $type  The purpose of the OTP
     * @param  int  $expiresInMinutes  Number of minutes before the code expires
     */
    public function __construct(
        public string $code,
        public string $type = 'auth',
        public int $expiresInMinutes = 10
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
        $name = property_exists($notifiable, 'first_name') && $notifiable->first_name
            ? $notifiable->first_name
            : null;

        $greeting = $name
            ? __('auth.mail.greeting', ['name' => $name])
            : __('auth.mail.greeting_generic');

        return (new MailMessage)
            ->subject(__('auth.mail.subject', ['code' => $this->code]))
            ->greeting($greeting)
            ->line(__('auth.mail.body'))
            ->line("# **{$this->code}**")
            ->line(__('auth.mail.expiry', ['minutes' => $this->expiresInMinutes]))
            ->line(__('auth.mail.warning'));
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
