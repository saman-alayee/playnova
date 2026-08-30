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
        public ?string $broadcastGroupId = null,
    ) {}

    public function handle(): void
    {
        $groupId = $this->broadcastGroupId ?: (string) \Illuminate\Support\Str::uuid();

        User::query()->select('id')->chunkById(200, function ($users) use ($groupId) {
            $now = now();
            $rows = $users->map(fn ($user) => [
                'user_id' => $user->id,
                'title' => $this->title,
                'message' => $this->message,
                'type' => 'broadcast',
                'broadcast_group_id' => $groupId,
                'is_read' => false,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            Notification::insert($rows);
        });
    }
}
