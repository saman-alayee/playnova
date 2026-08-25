<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\TournamentPrizeBatch;
use App\Models\TournamentPrizeEntry;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentPrizeService
{
    public function __construct(protected ActivityLogService $activity)
    {
    }

    /**
     * @param  list<array{user_id:int,rank?:int,kills?:int,team_label?:string,seat_number?:int}>  $rankedEntries
     */
    public function submitPendingBatch(Tournament $tournament, int $winnerUserId, array $rankedEntries = []): TournamentPrizeBatch
    {
        return DB::transaction(function () use ($tournament, $winnerUserId, $rankedEntries) {
            $lockedTournament = Tournament::query()->whereKey($tournament->id)->lockForUpdate()->firstOrFail();

            $existing = TournamentPrizeBatch::query()
                ->where('tournament_id', $lockedTournament->id)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->isPaid()) {
                throw new RuntimeException('جوایز این مسابقه قبلاً واریز شده است.');
            }

            if ($existing && ! $existing->isPending()) {
                throw new RuntimeException('دسته جوایز این مسابقه در حال بررسی است.');
            }

            if ($existing) {
                TournamentPrizeEntry::query()->where('batch_id', $existing->id)->delete();
                $batch = $existing;
            } else {
                $batch = TournamentPrizeBatch::create([
                    'tournament_id' => $lockedTournament->id,
                    'status' => TournamentPrizeBatch::STATUS_PENDING,
                ]);
            }

            $entries = $this->buildSuggestedEntries($lockedTournament, $rankedEntries, $winnerUserId);
            $total = 0.0;

            foreach ($entries as $entry) {
                TournamentPrizeEntry::create([
                    'batch_id' => $batch->id,
                    ...$entry,
                ]);
                $total += (float) $entry['prize_amount'];
            }

            $batch->update([
                'status' => TournamentPrizeBatch::STATUS_PENDING,
                'winner_user_id' => $winnerUserId,
                'total_amount' => round($total, 2),
                'approved_by' => null,
                'approved_at' => null,
                'paid_at' => null,
            ]);

            return $batch->fresh(['entries.user', 'winner', 'approver', 'tournament']);
        });
    }

    /**
     * @param  list<array{user_id:int,rank?:int,kills?:int,team_label?:string,seat_number?:int}>  $rankedEntries
     * @return list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int,prize_amount:float,metadata:?array}>
     */
    protected function buildSuggestedEntries(Tournament $tournament, array $rankedEntries, int $winnerUserId): array
    {
        if ($rankedEntries === []) {
            $winnerReg = Registration::query()
                ->where('tournament_id', $tournament->id)
                ->where('user_id', $winnerUserId)
                ->whereNotNull('seat_number')
                ->first();

            return [[
                'user_id' => $winnerUserId,
                'rank' => 1,
                'kills' => null,
                'team_label' => $winnerReg ? $tournament->seatDisplayLabel((int) $winnerReg->seat_number) : null,
                'seat_number' => $winnerReg?->seat_number,
                'prize_amount' => (float) $tournament->prize_pool,
                'metadata' => null,
            ]];
        }

        return collect($rankedEntries)
            ->map(function (array $row) use ($tournament) {
                $rank = isset($row['rank']) ? (int) $row['rank'] : null;
                $seatNumber = isset($row['seat_number']) ? (int) $row['seat_number'] : null;
                $teamLabel = $row['team_label'] ?? ($seatNumber ? $tournament->seatDisplayLabel($seatNumber) : null);

                $amount = ($rank === 1) ? (float) $tournament->prize_pool : 0.0;

                return [
                    'user_id' => (int) $row['user_id'],
                    'rank' => $rank,
                    'kills' => isset($row['kills']) ? (int) $row['kills'] : null,
                    'team_label' => $teamLabel,
                    'seat_number' => $seatNumber,
                    'prize_amount' => $amount,
                    'metadata' => $row['metadata'] ?? null,
                ];
            })
            ->unique('user_id')
            ->values()
            ->all();
    }

    /** @param  list<array{id:int,prize_amount:float}>  $updates */
    public function updateEntryAmounts(TournamentPrizeBatch $batch, array $updates): TournamentPrizeBatch
    {
        if (! $batch->isPending()) {
            throw new RuntimeException('فقط جوایز در انتظار تأیید قابل ویرایش هستند.');
        }

        return DB::transaction(function () use ($batch, $updates) {
            $locked = TournamentPrizeBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            foreach ($updates as $update) {
                TournamentPrizeEntry::query()
                    ->where('batch_id', $locked->id)
                    ->where('id', (int) $update['id'])
                    ->update(['prize_amount' => max(0, (float) $update['prize_amount'])]);
            }

            $total = (float) TournamentPrizeEntry::query()->where('batch_id', $locked->id)->sum('prize_amount');
            $locked->update(['total_amount' => round($total, 2)]);

            return $locked->fresh(['entries.user', 'winner', 'approver', 'tournament']);
        });
    }

    public function approveAndPay(TournamentPrizeBatch $batch, User $admin): TournamentPrizeBatch
    {
        return DB::transaction(function () use ($batch, $admin) {
            $locked = TournamentPrizeBatch::query()->whereKey($batch->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isPending()) {
                throw new RuntimeException('این دسته جوایز قبلاً تأیید یا واریز شده است.');
            }

            $locked->load(['entries.user', 'tournament']);
            $tournament = $locked->tournament;

            foreach ($locked->entries as $entry) {
                $amount = (float) $entry->prize_amount;
                if ($amount <= 0) {
                    continue;
                }

                $user = User::query()->whereKey($entry->user_id)->lockForUpdate()->firstOrFail();
                $referenceId = "prize_{$tournament->id}_{$entry->id}";

                $user->creditWallet(
                    $amount,
                    'prize',
                    "جایزه مسابقه: {$tournament->title}" . ($entry->team_label ? " ({$entry->team_label})" : ''),
                    $referenceId
                );

                $this->activity->logWallet($user, 'prize_paid', "دریافت جایزه مسابقه: {$tournament->title}", [
                    'tournament_id' => $tournament->id,
                    'batch_id' => $locked->id,
                    'entry_id' => $entry->id,
                    'amount' => $amount,
                    'rank' => $entry->rank,
                ], $admin);
            }

            $locked->update([
                'status' => TournamentPrizeBatch::STATUS_PAID,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'paid_at' => now(),
            ]);

            return $locked->fresh(['entries.user', 'winner', 'approver', 'tournament']);
        });
    }

    public function findForTournament(Tournament $tournament): ?TournamentPrizeBatch
    {
        return TournamentPrizeBatch::query()
            ->where('tournament_id', $tournament->id)
            ->with(['entries.user:id,username,cod_id', 'winner:id,username', 'approver:id,username'])
            ->first();
    }
}
