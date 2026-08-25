<?php

namespace App\Services;

use App\Models\TeamInvite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TeamInviteService
{
    private const CACHE_TTL_SECONDS = 5;

    public function pendingForUser(int $userId): Collection
    {
        return Cache::remember(
            $this->cacheKey($userId, 'pending'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->activePendingQuery()
                ->where('invitee_id', $userId)
                ->latest()
                ->get()
        );
    }

    public function sentForUser(int $userId): Collection
    {
        return Cache::remember(
            $this->cacheKey($userId, 'sent'),
            self::CACHE_TTL_SECONDS,
            fn () => $this->activePendingQuery()
                ->where('inviter_id', $userId)
                ->latest()
                ->get()
        );
    }

    public function forgetForUser(int $userId): void
    {
        Cache::forget($this->cacheKey($userId, 'pending'));
        Cache::forget($this->cacheKey($userId, 'sent'));
    }

    protected function activePendingQuery()
    {
        return TeamInvite::with(['tournament', 'inviter', 'invitee'])
            ->where('status', TeamInvite::STATUS_PENDING)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            });
    }

    private function cacheKey(int $userId, string $type): string
    {
        return "team_invites:{$userId}:{$type}";
    }
}
