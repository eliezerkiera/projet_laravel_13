<?php

namespace App\Jobs;

use App\Notifications\SendOtpNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendOtpEmail implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $email,
        public string $code,
        public string $type = 'auth',
        public int $expiresInMinutes = 10
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Notification::route('mail', $this->email)->notify(
            new SendOtpNotification($this->code, $this->type, $this->expiresInMinutes)
        );
    }
}
