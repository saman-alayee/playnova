<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\Tournament;
use App\Models\TournamentPrizeBatch;
use App\Models\TournamentPrizeEntry;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Services\TournamentPrizeTableParser;
use App\Support\PlacementRankCompactor;
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
                'on_leaderboard' => true,
                'confirmation_status' => 'confirmed',
                'confirmation_reason' => 'leaderboard',
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
                        'on_leaderboard' => true,
                        'confirmation_status' => 'confirmed',
                        'confirmation_reason' => 'leaderboard',
                    ];
                })
                ->unique('user_id')
                ->values()
                ->all();
        }

        $rows = PlacementRankCompactor::compact($rows, $tournament->seatMode());
        $rows = $this->markRegistrationStatus($tournament, $rows, $registrations);
        $rows = $this->appendAbsentTeammates($tournament, $rows, $registrations);

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
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, Registration>  $registrations
     * @return list<array<string, mixed>>
     */
    protected function markRegistrationStatus(Tournament $tournament, array $rows, $registrations): array
    {
        foreach ($rows as $index => $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $reg = $registrations->get($userId);
            $seat = isset($row['seat_number']) ? (int) $row['seat_number'] : ($reg?->seat_number ? (int) $reg->seat_number : 0);

            if (! $reg || $seat < 1) {
                $rows[$index]['confirmation_status'] = 'unconfirmed';
                $rows[$index]['confirmation_reason'] = 'seat_mismatch';
                $rows[$index]['on_leaderboard'] = (bool) ($row['on_leaderboard'] ?? false);
                continue;
            }

            $rows[$index]['seat_number'] = $seat;
            $rows[$index]['team_label'] = $row['team_label'] ?? $tournament->seatDisplayLabel($seat);
            $rows[$index]['on_leaderboard'] = (bool) ($row['on_leaderboard'] ?? true);
            $rows[$index]['confirmation_status'] = $rows[$index]['on_leaderboard'] ? 'confirmed' : 'unconfirmed';
            $rows[$index]['confirmation_reason'] = $rows[$index]['on_leaderboard'] ? 'leaderboard' : 'not_on_leaderboard';
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  \Illuminate\Support\Collection<int, Registration>  $registrations
     * @return list<array<string, mixed>>
     */
    protected function appendAbsentTeammates(Tournament $tournament, array $rows, $registrations): array
    {
        if ($tournament->seatMode() <= 1) {
            return $rows;
        }

        $presentIds = [];
        $rankTeams = [];
        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);
            $rank = (int) ($row['rank'] ?? 0);
            $presentIds[$userId] = true;
            if ($rank < 1 || (($row['confirmation_status'] ?? '') === 'unconfirmed')) {
                continue;
            }

            $seat = isset($row['seat_number']) ? (int) $row['seat_number'] : 0;
            $team = $tournament->teamNumberForSeat($seat);
            if ($team) {
                $rankTeams[$rank][$team] = true;
            }
        }

        foreach ($registrations as $reg) {
            $userId = (int) $reg->user_id;
            if (isset($presentIds[$userId])) {
                continue;
            }

            $seat = (int) $reg->seat_number;
            $team = $tournament->teamNumberForSeat($seat);
            if (! $team) {
                continue;
            }

            foreach ($rankTeams as $rank => $teams) {
                if (! isset($teams[$team])) {
                    continue;
                }

                $rows[] = [
                    'user_id' => $userId,
                    'rank' => $rank,
                    'kills' => null,
                    'team_label' => $tournament->seatDisplayLabel($seat),
                    'seat_number' => $seat,
                    'on_leaderboard' => false,
                    'confirmation_status' => 'unconfirmed',
                    'confirmation_reason' => 'not_on_leaderboard',
                ];
                $presentIds[$userId] = true;
                break;
            }
        }

        return $rows;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array<int, float>  $prizeTable
     * @return list<array{user_id:int,rank:?int,kills:?int,team_label:?string,seat_number:?int,prize_amount:float,metadata:?array}>
     */
    protected function assignPrizeAmounts(Tournament $tournament, array $rows, array $prizeTable): array
    {
        $seatMode = max(1, $tournament->seatMode());
        $amounts = array_fill(0, count($rows), 0.0);

        foreach ($rows as $index => $row) {
            $rank = (int) ($row['rank'] ?? 0);
            $confirmed = ($row['confirmation_status'] ?? 'confirmed') !== 'unconfirmed';
            if ($rank < 1 || ! $confirmed) {
                continue;
            }

            $teamTotal = $this->prizeTableParser->amountForRank($prizeTable, $rank, 0);
            $amounts[$index] = $this->prizeTableParser->sharePerRosterSlot($teamTotal, $seatMode);
        }

        $result = [];
        foreach ($rows as $index => $row) {
            $rank = isset($row['rank']) ? (int) $row['rank'] : null;
            $teamTotal = $rank ? $this->prizeTableParser->amountForRank($prizeTable, $rank, 0) : 0.0;
            $status = ($row['confirmation_status'] ?? 'confirmed') === 'unconfirmed' ? 'unconfirmed' : 'confirmed';
            $onLeaderboard = (bool) ($row['on_leaderboard'] ?? ($status === 'confirmed'));

            $result[] = [
                'user_id' => (int) $row['user_id'],
                'rank' => $rank && $rank > 0 ? $rank : null,
                'kills' => isset($row['kills']) ? (int) $row['kills'] : null,
                'team_label' => $row['team_label'] ?? null,
                'seat_number' => isset($row['seat_number']) ? (int) $row['seat_number'] : null,
                'prize_amount' => $amounts[$index],
                'metadata' => [
                    'prize_rank' => $rank && $rank > 0 ? $rank : null,
                    'team_prize' => $teamTotal,
                    'player_share' => $amounts[$index],
                    'seat_mode' => $seatMode,
                    'on_leaderboard' => $onLeaderboard,
                    'confirmation_status' => $status,
                    'confirmation_reason' => $row['confirmation_reason'] ?? ($status === 'confirmed' ? 'leaderboard' : 'not_on_leaderboard'),
                ],
            ];
        }

        usort($result, function (array $left, array $right) {
            $rankCmp = ($left['rank'] ?? 9999) <=> ($right['rank'] ?? 9999);
            if ($rankCmp !== 0) {
                return $rankCmp;
            }

            $statusCmp = (($left['metadata']['confirmation_status'] ?? '') === 'unconfirmed')
                <=> (($right['metadata']['confirmation_status'] ?? '') === 'unconfirmed');
            if ($statusCmp !== 0) {
                return $statusCmp;
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
            $payable = 0.0;
            foreach ($locked->entries as $entry) {
                $amount = (float) $entry->prize_amount;
                $status = is_array($entry->metadata) ? ($entry->metadata['confirmation_status'] ?? 'confirmed') : 'confirmed';
                if ($amount > 0 && $status !== 'unconfirmed') {
                    $payable += $amount;
                }
            }
            $payable = round($payable, 0);

            if ($budget > 0 && $payable - $budget > 0.5) {
                throw new RuntimeException(
                    'مجموع جوایز قابل واریز (' . number_format($payable) . ' تومان) بیشتر از بودجه مسابقه (' . number_format($budget) . ' تومان) است.'
                );
            }

            foreach ($locked->entries as $entry) {
                $amount = (float) $entry->prize_amount;
                $status = is_array($entry->metadata) ? ($entry->metadata['confirmation_status'] ?? 'confirmed') : 'confirmed';
                if ($amount <= 0 || $status === 'unconfirmed') {
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
