<?php

namespace App\Services;

use App\Models\TeamInvite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TeamInviteService
{
    private const CACHE_TTL_SECONDS = 30;

    public function pendingForUser(int $userId): Collection
    {
        return Cache::remember(
            $this->cacheKey($userId, 'pending'),
            self::CACHE_TTL_SECONDS,
            fn () => TeamInvite::with(['tournament', 'inviter'])
                ->where('invitee_id', $userId)
                ->where('status', TeamInvite::STATUS_PENDING)
                ->latest()
                ->get()
        );
    }

    public function sentForUser(int $userId): Collection
    {
        return Cache::remember(
            $this->cacheKey($userId, 'sent'),
            self::CACHE_TTL_SECONDS,
            fn () => TeamInvite::with(['tournament', 'invitee'])
                ->where('inviter_id', $userId)
                ->where('status', TeamInvite::STATUS_PENDING)
                ->latest()
                ->get()
        );
    }

    public function forgetForUser(int $userId): void
    {
        Cache::forget($this->cacheKey($userId, 'pending'));
        Cache::forget($this->cacheKey($userId, 'sent'));
    }

    private function cacheKey(int $userId, string $type): string
    {
        return "team_invites:{$userId}:{$type}";
    }
}
