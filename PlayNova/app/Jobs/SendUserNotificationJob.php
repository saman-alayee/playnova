<?php

namespace App\Jobs;

use App\Models\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendUserNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $userId,
        public string $title,
        public string $message,
        public string $type = 'general',
    ) {}

    public function handle(): void
    {
        Notification::create([
            'user_id' => $this->userId,
            'title' => $this->title,
            'message' => $this->message,
            'type' => $this->type,
            'is_read' => false,
        ]);
    }
}
