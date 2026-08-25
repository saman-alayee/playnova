<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Services\TournamentEntryFeeService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class TournamentRegistrationService
{
    public function __construct(
        protected TournamentEntryFeeService $fees,
        protected ActivityLogService $activity,
    ) {
    }

    /**
     * Atomically charge entry fee and assign seat. No seat without successful payment.
     */
    public function confirmSoloSeat(User $user, Tournament $tournament, int $seatNumber): Registration
    {
        return DB::transaction(function () use ($user, $tournament, $seatNumber) {
            $lockedTournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if (! $lockedTournament->acceptsRegistration()) {
                throw new RuntimeException('registration_closed');
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $registration = Registration::query()
                ->where('user_id', $lockedUser->id)
                ->where('tournament_id', $lockedTournament->id)
                ->lockForUpdate()
                ->first();

            if (! $registration) {
                throw new RuntimeException('not_registered');
            }

            if ($registration->seat_number !== null) {
                throw new RuntimeException('already_selected');
            }

            if ($this->userHasConfirmedSeat($lockedUser->id, $lockedTournament->id, $registration->id)) {
                throw new RuntimeException('already_selected');
            }

            $taken = Registration::query()
                ->where('tournament_id', $lockedTournament->id)
                ->where('seat_number', $seatNumber)
                ->lockForUpdate()
                ->exists();

            if ($taken) {
                throw new RuntimeException('seat_taken');
            }

            if ($this->seatedCount($lockedTournament->id) >= (int) $lockedTournament->capacity) {
                throw new RuntimeException('tournament_full');
            }

            try {
                $this->fees->charge($lockedUser, $lockedTournament);
            } catch (InvalidArgumentException) {
                throw new RuntimeException('insufficient_wallet');
            }

            $registration->update([
                'seat_number' => $seatNumber,
                'status' => 'confirmed',
                'reservation_type' => $registration->reservation_type ?? 'solo',
            ]);

            $this->syncRegisteredCount($lockedTournament);

            $user->wallet = $lockedUser->wallet;

            $this->activity->logTournament($lockedUser, 'tournament_joined', "ثبت‌نام در مسابقه: {$lockedTournament->title}", [
                'tournament_id' => $lockedTournament->id,
                'seat_number' => $seatNumber,
                'entry_fee' => (float) $lockedTournament->entry_fee,
            ]);

            return $registration->fresh(['tournament']);
        });
    }

    public function createPendingIntent(User $user, Tournament $tournament): Registration
    {
        return DB::transaction(function () use ($user, $tournament) {
            $lockedTournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            if (! $lockedTournament->acceptsRegistration()) {
                throw new RuntimeException('registration_closed');
            }

            if ($this->seatedCount($lockedTournament->id) >= (int) $lockedTournament->capacity) {
                throw new RuntimeException('tournament_full');
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = Registration::query()
                ->where('user_id', $lockedUser->id)
                ->where('tournament_id', $lockedTournament->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->seat_number !== null) {
                    throw new RuntimeException('already_registered');
                }

                return $existing;
            }

            if ($lockedUser->wallet < (float) $lockedTournament->entry_fee) {
                throw new RuntimeException('insufficient_wallet');
            }

            return Registration::create([
                'user_id' => $lockedUser->id,
                'tournament_id' => $lockedTournament->id,
                'status' => 'waiting',
                'reservation_type' => 'solo',
            ]);
        });
    }

    public function seatedCount(int $tournamentId): int
    {
        return Registration::query()
            ->where('tournament_id', $tournamentId)
            ->whereNotNull('seat_number')
            ->count();
    }

    public function syncRegisteredCount(Tournament $tournament): void
    {
        $tournament->update([
            'registered_count' => $this->seatedCount($tournament->id),
        ]);
    }

    protected function userHasConfirmedSeat(int $userId, int $tournamentId, ?int $exceptRegistrationId = null): bool
    {
        $query = Registration::query()
            ->where('user_id', $userId)
            ->where('tournament_id', $tournamentId)
            ->whereNotNull('seat_number');

        if ($exceptRegistrationId) {
            $query->where('id', '!=', $exceptRegistrationId);
        }

        return $query->exists();
    }
}
