<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Services\TournamentEntryFeeService;
use App\Support\SeatAdvisoryLock;
use Illuminate\Database\QueryException;
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
        return SeatAdvisoryLock::run($tournament->id, [$seatNumber], function () use ($user, $tournament, $seatNumber) {
            return DB::transaction(function () use ($user, $tournament, $seatNumber) {
                $freshTournament = Tournament::query()->whereKey($tournament->id)->firstOrFail();

                if (! $freshTournament->acceptsRegistration()) {
                    throw new RuntimeException('registration_closed');
                }

                $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

                $registration = Registration::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('tournament_id', $freshTournament->id)
                    ->lockForUpdate()
                    ->first();

                if (! $registration) {
                    throw new RuntimeException('not_registered');
                }

                if ($registration->seat_number !== null) {
                    throw new RuntimeException('already_selected');
                }

                if (($registration->reservation_type ?? 'solo') === 'team') {
                    throw new RuntimeException('team_use_invite');
                }

                if ($this->userHasConfirmedSeat($lockedUser->id, $freshTournament->id, $registration->id)) {
                    throw new RuntimeException('already_selected');
                }

                $taken = Registration::query()
                    ->where('tournament_id', $freshTournament->id)
                    ->where('seat_number', $seatNumber)
                    ->lockForUpdate()
                    ->exists();

                if ($taken) {
                    throw new RuntimeException('seat_taken');
                }

                if ($this->seatedCount($freshTournament->id) >= (int) $freshTournament->capacity) {
                    throw new RuntimeException('tournament_full');
                }

                try {
                    $this->fees->charge($lockedUser, $freshTournament);
                } catch (InvalidArgumentException) {
                    throw new RuntimeException('insufficient_wallet');
                }

                $this->markSeatConfirmed($registration, $seatNumber, [
                    'reservation_type' => $registration->reservation_type ?? 'solo',
                ]);

                $this->syncRegisteredCount($freshTournament);

                $user->wallet = $lockedUser->wallet;

                $this->activity->logTournament($lockedUser, 'tournament_joined', "ثبت‌نام در مسابقه: {$freshTournament->title}", [
                    'tournament_id' => $freshTournament->id,
                    'seat_number' => $seatNumber,
                    'entry_fee' => (float) $freshTournament->entry_fee,
                ]);

                return $registration->fresh(['tournament']);
            });
        });
    }

    public function createPendingIntent(User $user, Tournament $tournament, string $reservationType = 'solo'): Registration
    {
        $reservationType = in_array($reservationType, ['solo', 'team'], true) ? $reservationType : 'solo';

        return DB::transaction(function () use ($user, $tournament, $reservationType) {
            $freshTournament = Tournament::query()->whereKey($tournament->id)->firstOrFail();

            if (! $freshTournament->acceptsRegistration()) {
                throw new RuntimeException('registration_closed');
            }

            if ($reservationType === 'team' && ! $freshTournament->supportsTeamInvite()) {
                throw new RuntimeException('team_not_supported');
            }

            if ($this->seatedCount($freshTournament->id) >= (int) $freshTournament->capacity) {
                throw new RuntimeException('tournament_full');
            }

            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $existing = Registration::query()
                ->where('user_id', $lockedUser->id)
                ->where('tournament_id', $freshTournament->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($existing->seat_number !== null) {
                    throw new RuntimeException('already_registered');
                }

                if (($existing->reservation_type ?? 'solo') !== $reservationType) {
                    $existing->update(['reservation_type' => $reservationType]);
                }

                return $existing->fresh();
            }

            if ($lockedUser->wallet < (float) $freshTournament->entry_fee) {
                throw new RuntimeException('insufficient_wallet');
            }

            try {
                return Registration::create([
                    'user_id' => $lockedUser->id,
                    'tournament_id' => $freshTournament->id,
                    'status' => 'waiting',
                    'reservation_type' => $reservationType,
                ]);
            } catch (QueryException $e) {
                if (! $this->isUniqueViolation($e)) {
                    throw $e;
                }

                $concurrent = Registration::query()
                    ->where('user_id', $lockedUser->id)
                    ->where('tournament_id', $freshTournament->id)
                    ->first();

                if ($concurrent) {
                    return $concurrent;
                }

                throw $e;
            }
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

    /**
     * @param  array<string, mixed>  $extra
     */
    public function markSeatConfirmed(Registration $registration, int $seatNumber, array $extra = []): void
    {
        try {
            $registration->update(array_merge([
                'seat_number' => $seatNumber,
                'status' => 'confirmed',
            ], $extra));
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new RuntimeException('seat_taken');
            }

            throw $e;
        }
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    public function createConfirmedSeat(int $userId, int $tournamentId, int $seatNumber, array $extra = []): Registration
    {
        try {
            return Registration::create(array_merge([
                'user_id' => $userId,
                'tournament_id' => $tournamentId,
                'seat_number' => $seatNumber,
                'status' => 'confirmed',
            ], $extra));
        } catch (QueryException $e) {
            if ($this->isUniqueViolation($e)) {
                throw new RuntimeException('seat_taken');
            }

            throw $e;
        }
    }

    public function isUniqueViolation(QueryException $e): bool
    {
        $sqlState = (string) ($e->errorInfo[0] ?? '');
        $driverCode = (int) ($e->errorInfo[1] ?? 0);

        return $sqlState === '23000'
            || $driverCode === 1062
            || str_contains($e->getMessage(), 'UNIQUE constraint')
            || str_contains($e->getMessage(), 'Duplicate entry');
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
