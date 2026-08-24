<?php

namespace App\Jobs;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class BroadcastNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 600;

    public function __construct(
        public string $title,
        public string $message,
    ) {}

    public function handle(): void
    {
        User::query()->select('id')->chunkById(200, function ($users) {
            $now = now();
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'title' => $this->title,
                'message' => $this->message,
                'type' => 'broadcast',
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Notification::insert($rows);
        });
    }
}
