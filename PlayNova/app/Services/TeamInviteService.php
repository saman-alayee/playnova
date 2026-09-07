<?php

namespace App\Services;

use App\Models\TeamInvite;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TeamInviteService
{
    private const CACHE_TTL_SECONDS = 5;

    public function pendingForUser(int $userId): Collection
    {
        $this->expireOverdueForUser($userId);

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
        $this->expireOverdueForUser($userId);

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

    public function expireOverdueForUser(int $userId): void
    {
        $invites = TeamInvite::query()
            ->where('status', TeamInvite::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->where(function ($query) use ($userId) {
                $query->where('inviter_id', $userId)->orWhere('invitee_id', $userId);
            })
            ->get();

        foreach ($invites as $invite) {
            $this->expireIfOverdue($invite);
        }
    }

    public function expireIfOverdue(TeamInvite $invite): bool
    {
        if ($invite->team_group_id) {
            return $this->expireGroup($invite);
        }

        return $this->expireSingle($invite);
    }

    protected function expireSingle(TeamInvite $invite): bool
    {
        $expired = false;

        DB::transaction(function () use ($invite, &$expired) {
            $lockedInvite = TeamInvite::query()->whereKey($invite->id)->lockForUpdate()->first();

            if (! $lockedInvite || ! $lockedInvite->isOpen()) {
                return;
            }

            if ($lockedInvite->expires_at && $lockedInvite->expires_at->isFuture()) {
                return;
            }

            $lockedInvite->update(['status' => TeamInvite::STATUS_EXPIRED]);
            $expired = true;
        });

        if ($expired) {
            $this->forgetRelatedCaches($invite);
        }

        return $expired;
    }

    protected function expireGroup(TeamInvite $invite): bool
    {
        $expired = false;
        $related = collect();

        DB::transaction(function () use ($invite, &$expired, &$related) {
            $groupInvites = TeamInvite::query()
                ->where('team_group_id', $invite->team_group_id)
                ->lockForUpdate()
                ->get();

            if ($groupInvites->isEmpty()) {
                return;
            }

            $first = $groupInvites->first();
            if ($first->expires_at && $first->expires_at->isFuture()) {
                return;
            }

            if ($groupInvites->contains(fn (TeamInvite $groupInvite) => $groupInvite->seat_number_invitee !== null)) {
                return;
            }

            foreach ($groupInvites as $groupInvite) {
                if ($groupInvite->isOpen()) {
                    $groupInvite->update(['status' => TeamInvite::STATUS_EXPIRED]);
                    $expired = true;
                }
            }

            $related = $groupInvites;
        });

        if ($expired) {
            $this->forgetRelatedCaches($invite, $related);
        }

        return $expired;
    }

    /**
     * @param  Collection<int, TeamInvite>|null  $related
     */
    protected function forgetRelatedCaches(TeamInvite $invite, ?Collection $related = null): void
    {
        $this->forgetForUser((int) $invite->inviter_id);
        $this->forgetForUser((int) $invite->invitee_id);

        $related?->each(function (TeamInvite $groupInvite) {
            $this->forgetForUser((int) $groupInvite->inviter_id);
            $this->forgetForUser((int) $groupInvite->invitee_id);
        });
    }

    protected function activePendingQuery()
    {
        return TeamInvite::with(['tournament', 'inviter', 'invitee'])->activePending();
    }

    private function cacheKey(int $userId, string $type): string
    {
        return "team_invites:{$userId}:{$type}";
    }
}
