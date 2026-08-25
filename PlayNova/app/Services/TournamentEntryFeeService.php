<?php

namespace App\Services;

use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use InvalidArgumentException;

class TournamentEntryFeeService
{
    public function __construct(protected ActivityLogService $activity)
    {
    }

    public function feeReference(int $tournamentId, int $userId): string
    {
        return "tournament_fee_{$tournamentId}_{$userId}";
    }

    public function refundReference(int $tournamentId, int $userId): string
    {
        return "tournament_refund_{$tournamentId}_{$userId}";
    }

    public function hasPaid(User $user, Tournament $tournament): bool
    {
        return $this->findCompletedFee($user, $tournament) !== null;
    }

    /** Idempotent fee charge — must run inside a DB transaction with user row locked. */
    public function charge(User $user, Tournament $tournament): void
    {
        $reference = $this->feeReference($tournament->id, $user->id);
        $fee = (float) $tournament->entry_fee;

        if ($fee <= 0) {
            return;
        }

        if ($this->hasPaid($user, $tournament)) {
            return;
        }

        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        if ($this->hasPaid($lockedUser, $tournament)) {
            $user->wallet = $lockedUser->wallet;

            return;
        }

        try {
            $lockedUser->debitWallet(
                $fee,
                'fee',
                "هزینه ثبت‌نام در مسابقه: {$tournament->title}",
                $reference
            );
        } catch (InvalidArgumentException) {
            throw new InvalidArgumentException('insufficient_wallet');
        }

        $user->wallet = $lockedUser->wallet;

        $this->activity->logWallet($lockedUser, 'entry_fee_charged', "کسر ورودی مسابقه: {$tournament->title}", [
            'tournament_id' => $tournament->id,
            'amount' => $fee,
            'reference_id' => $reference,
        ]);
    }

    /** Refund entry fee for this tournament/user if it was actually charged. */
    public function refundIfPaid(User $user, Tournament $tournament): bool
    {
        $refundReference = $this->refundReference($tournament->id, $user->id);

        $alreadyRefunded = Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'refund')
            ->where('status', 'completed')
            ->where('reference_id', $refundReference)
            ->exists();

        if ($alreadyRefunded) {
            return false;
        }

        $feeTx = $this->findCompletedFee($user, $tournament);
        if (! $feeTx) {
            return false;
        }

        $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

        $lockedUser->creditWallet(
            (float) $feeTx->amount,
            'refund',
            "بازگشت هزینه ثبت‌نام (انصراف): {$tournament->title}",
            $refundReference
        );

        $user->wallet = $lockedUser->wallet;

        $this->activity->logWallet($lockedUser, 'entry_fee_refunded', "بازگشت ورودی مسابقه: {$tournament->title}", [
            'tournament_id' => $tournament->id,
            'amount' => (float) $feeTx->amount,
        ]);

        return true;
    }

    protected function findCompletedFee(User $user, Tournament $tournament): ?Transaction
    {
        return Transaction::query()
            ->where('user_id', $user->id)
            ->where('type', 'fee')
            ->where('status', 'completed')
            ->where('reference_id', $this->feeReference($tournament->id, $user->id))
            ->first();
    }
}
