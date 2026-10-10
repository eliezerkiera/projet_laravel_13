<?php

namespace App\Jobs;

use App\Notifications\ExistingAccountNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Notification;

class SendExistingAccountNotice implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $email
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Notification::route('mail', $this->email)->notify(
            new ExistingAccountNotification
        );
    }
}
