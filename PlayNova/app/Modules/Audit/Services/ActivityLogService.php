<?php

namespace App\Modules\Audit\Services;

use App\Models\ActivityLog;
use App\Models\User;

class ActivityLogService
{
    public function log(
        User $user,
        string $category,
        string $action,
        ?string $description = null,
        array $metadata = [],
        ?User $actor = null,
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $user->id,
            'category' => $category,
            'action' => $action,
            'description' => $description,
            'metadata' => $metadata ?: null,
            'actor_id' => $actor?->id,
            'ip_address' => request()->ip(),
            'created_at' => now(),
        ]);
    }

    public function logWallet(User $user, string $action, string $description, array $metadata = [], ?User $actor = null): void
    {
        $this->log($user, 'wallet', $action, $description, $metadata, $actor);
    }

    public function logTournament(User $user, string $action, string $description, array $metadata = []): void
    {
        $this->log($user, 'tournament', $action, $description, $metadata);
    }

    public function logProfile(User $user, string $action, string $description, array $metadata = [], ?User $actor = null): void
    {
        $this->log($user, 'profile', $action, $description, $metadata, $actor);
    }

    public function logAuth(User $user, string $action, ?string $description = null): void
    {
        $this->log($user, 'auth', $action, $description);
    }
}
