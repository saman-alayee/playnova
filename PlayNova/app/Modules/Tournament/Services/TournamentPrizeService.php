<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\TournamentPrizeBatch;
use App\Models\TournamentPrizeEntry;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Services\TournamentPrizeTableParser;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class TournamentPrizeService
{
    public function __construct(
        protected ActivityLogService $activity,
        protected TournamentPrizeTableParser $prizeTableParser,
    ) {
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
        $prizeTable = $this->prizeTableFor($tournament);
        $registrations = Registration::query()
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->get()
            ->keyBy('user_id');

        if ($rankedEntries === []) {
            $winnerReg = $registrations->get($winnerUserId);
            $rows = [[
                'user_id' => $winnerUserId,
                'rank' => 1,
                'kills' => null,
                'team_label' => $winnerReg ? $tournament->seatDisplayLabel((int) $winnerReg->seat_number) : null,
                'seat_number' => $winnerReg?->seat_number,
            ]];
        } else {
            $rows = collect($rankedEntries)
                ->map(function (array $row) use ($tournament, $registrations) {
                    $userId = (int) $row['user_id'];
                    $reg = $registrations->get($userId);
                    $seatNumber = isset($row['seat_number'])
                        ? (int) $row['seat_number']
                        : ($reg?->seat_number ? (int) $reg->seat_number : null);
                    $rank = isset($row['rank']) ? (int) $row['rank'] : null;

                    return [
                        'user_id' => $userId,
                        'rank' => $rank && $rank > 0 ? $rank : null,
                        'kills' => isset($row['kills']) ? (int) $row['kills'] : null,
                        'team_label' => $row['team_label'] ?? ($seatNumber ? $tournament->seatDisplayLabel($seatNumber) : null),
                        'seat_number' => $seatNumber,
                    ];
                })
                ->unique('user_id')
                ->values()
                ->all();
        }

        $rows = $this->expandTeammates($tournament, $rows, $registrations);

        return $this->assignPrizeAmounts($tournament, $rows, $prizeTable);
    }

    /** @return array<int, float> */
    public function prizeTableFor(Tournament $tournament): array
    {
        $pool = (float) ($tournament->prize_pool ?? 0);
        $fromDescription = $this->prizeTableParser->parseWithPool((string) $tournament->description, $pool);
        $configured = $tournament->prizeRanksTable();

        if ($fromDescription !== []) {
            return $fromDescription + $configured;
        }

        return $configured;
    }

    /**
     * @param  list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int}>  $rows
     * @param  \Illuminate\Support\Collection<int, Registration>  $registrations
     * @return list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int}>
     */
    protected function expandTeammates(Tournament $tournament, array $rows, $registrations): array
    {
        if ($tournament->seatMode() <= 1) {
            return $rows;
        }

        $byUser = [];
        foreach ($rows as $row) {
            $byUser[(int) $row['user_id']] = $row;
        }

        $teamRank = [];
        foreach ($byUser as $row) {
            $seat = isset($row['seat_number']) ? (int) $row['seat_number'] : null;
            if (! $seat) {
                $reg = $registrations->get((int) $row['user_id']);
                $seat = $reg?->seat_number ? (int) $reg->seat_number : null;
            }

            $team = $tournament->teamNumberForSeat($seat);
            $rank = isset($row['rank']) ? (int) $row['rank'] : 0;
            if (! $team || $rank < 1) {
                continue;
            }

            $teamRank[$team] = isset($teamRank[$team]) ? min($teamRank[$team], $rank) : $rank;
        }

        foreach ($registrations as $reg) {
            $team = $tournament->teamNumberForSeat((int) $reg->seat_number);
            if (! $team || ! isset($teamRank[$team])) {
                continue;
            }

            $userId = (int) $reg->user_id;
            $seatNumber = (int) $reg->seat_number;
            $label = $tournament->seatDisplayLabel($seatNumber);

            if (! isset($byUser[$userId])) {
                $byUser[$userId] = [
                    'user_id' => $userId,
                    'rank' => $teamRank[$team],
                    'kills' => null,
                    'team_label' => $label,
                    'seat_number' => $seatNumber,
                ];

                continue;
            }

            $byUser[$userId]['rank'] = $teamRank[$team];
            $byUser[$userId]['seat_number'] = $byUser[$userId]['seat_number'] ?: $seatNumber;
            $byUser[$userId]['team_label'] = $byUser[$userId]['team_label'] ?: $label;
        }

        return array_values($byUser);
    }

    /**
     * @param  list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int}>  $rows
     * @param  array<int, float>  $prizeTable
     * @return list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int,prize_amount:float,metadata:?array}>
     */
    protected function assignPrizeAmounts(Tournament $tournament, array $rows, array $prizeTable): array
    {
        $groups = [];
        foreach ($rows as $index => $row) {
            $rank = (int) ($row['rank'] ?? 0);
            $groups[$rank][] = $index;
        }

        $amounts = array_fill(0, count($rows), 0.0);
        foreach ($groups as $rank => $indexes) {
            if ($rank < 1) {
                continue;
            }

            $teamTotal = $this->prizeTableParser->amountForRank($prizeTable, $rank, 0);
            $shares = $this->prizeTableParser->splitAmongPlayers($teamTotal, count($indexes));
            foreach ($indexes as $shareIndex => $rowIndex) {
                $amounts[$rowIndex] = $shares[$shareIndex];
            }
        }

        $result = [];
        foreach ($rows as $index => $row) {
            $rank = isset($row['rank']) ? (int) $row['rank'] : null;
            $teamTotal = $rank ? $this->prizeTableParser->amountForRank($prizeTable, $rank, 0) : 0.0;

            $result[] = [
                'user_id' => (int) $row['user_id'],
                'rank' => $rank && $rank > 0 ? $rank : null,
                'kills' => isset($row['kills']) ? (int) $row['kills'] : null,
                'team_label' => $row['team_label'] ?? null,
                'seat_number' => isset($row['seat_number']) ? (int) $row['seat_number'] : null,
                'prize_amount' => $amounts[$index],
                'metadata' => $rank ? [
                    'prize_rank' => $rank,
                    'team_prize' => $teamTotal,
                    'player_share' => $amounts[$index],
                    'seat_mode' => $tournament->seatMode(),
                ] : null,
            ];
        }

        usort($result, function (array $left, array $right) {
            $rankCmp = ($left['rank'] ?? 9999) <=> ($right['rank'] ?? 9999);
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            return ($left['seat_number'] ?? 0) <=> ($right['seat_number'] ?? 0);
        });

        return $result;
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
            $locked->loadMissing('tournament');

            $ranks = [];
            foreach (TournamentPrizeEntry::query()->where('batch_id', $locked->id)->get(['rank', 'prize_amount']) as $entry) {
                $rank = (int) $entry->rank;
                if ($rank > 0 && (float) $entry->prize_amount > 0) {
                    $ranks[$rank] = (float) ($ranks[$rank] ?? 0) + (float) $entry->prize_amount;
                }
            }
            $locked->tournament?->update(['prize_ranks' => $ranks !== [] ? $ranks : null]);

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
            $budget = (float) ($tournament?->prize_pool ?? 0);
            $total = round((float) $locked->entries->sum('prize_amount'), 0);

            if ($budget > 0 && abs($total - $budget) > 0.5) {
                throw new RuntimeException(
                    'مجموع جوایز (' . number_format($total) . ' تومان) باید برابر بودجه مسابقه (' . number_format($budget) . ' تومان) باشد.'
                );
            }

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
