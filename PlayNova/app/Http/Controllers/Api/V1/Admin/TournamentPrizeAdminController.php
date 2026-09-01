<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Tournament;
use App\Models\TournamentPrizeBatch;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Modules\Tournament\Services\TournamentPrizeService;
use App\Support\IranDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class TournamentPrizeAdminController extends BaseApiController
{
    use AuthorizesAdmin;
    use InvalidatesAdminDashboard;

    public function show(Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        $batch = $prizes->findForTournament($tournament);

        if (! $batch) {
            return $this->success(null, 'دسته جایزه‌ای برای این مسابقه ثبت نشده است.');
        }

        return $this->success($this->serializeBatch($batch));
    }

    public function update(Request $request, Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        $batch = $prizes->findForTournament($tournament);
        if (! $batch) {
            return $this->error('دسته جایزه‌ای برای این مسابقه یافت نشد.', 404);
        }

        $validated = $request->validate([
            'entries' => 'required|array|min:1',
            'entries.*.id' => 'required|integer|exists:tournament_prize_entries,id',
            'entries.*.prize_amount' => 'required|numeric|min:0|max:999999999999',
        ]);

        try {
            $updated = $prizes->updateEntryAmounts($batch, $validated['entries']);
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        return $this->success($this->serializeBatch($updated), 'مبالغ جوایز به‌روزرسانی شد.');
    }

    public function approve(Request $request, Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        $batch = $prizes->findForTournament($tournament);
        if (! $batch) {
            return $this->error('دسته جایزه‌ای برای این مسابقه یافت نشد.', 404);
        }

        try {
            $paid = $prizes->approveAndPay($batch, $request->user());
        } catch (RuntimeException $e) {
            return $this->error($e->getMessage(), 422);
        }

        TournamentListingService::forgetHomeCache();
        TournamentListingService::forgetLeaderboardCache();
        $this->invalidateAdminDashboard();

        return $this->success($this->serializeBatch($paid), 'جوایز با موفقیت تأیید و واریز شدند.');
    }

    protected function serializeBatch(TournamentPrizeBatch $batch): array
    {
        $batch->loadMissing(['entries.user:id,username,cod_id', 'winner:id,username', 'approver:id,username', 'tournament:id,title,prize_pool']);

        return [
            'id' => $batch->id,
            'tournament_id' => $batch->tournament_id,
            'tournament_title' => $batch->tournament?->title,
            'prize_pool' => (float) ($batch->tournament?->prize_pool ?? 0),
            'status' => $batch->status,
            'status_label' => match ($batch->status) {
                TournamentPrizeBatch::STATUS_PENDING => 'در انتظار تأیید',
                TournamentPrizeBatch::STATUS_APPROVED => 'تأیید شده',
                TournamentPrizeBatch::STATUS_PAID => 'واریز شده',
                default => $batch->status,
            },
            'total_amount' => (float) $batch->total_amount,
            'winner' => $batch->winner ? [
                'id' => $batch->winner->id,
                'username' => $batch->winner->username,
            ] : null,
            'approved_by' => $batch->approver ? [
                'id' => $batch->approver->id,
                'username' => $batch->approver->username,
            ] : null,
            'approved_at' => $batch->approved_at?->toIso8601String(),
            'approved_at_display' => IranDate::formatString($batch->approved_at),
            'paid_at' => $batch->paid_at?->toIso8601String(),
            'paid_at_display' => IranDate::formatString($batch->paid_at),
            'created_at_display' => IranDate::formatString($batch->created_at),
            'entries' => $batch->entries->map(fn ($entry) => [
                'id' => $entry->id,
                'user_id' => $entry->user_id,
                'username' => $entry->user?->username,
                'cod_id' => $entry->user?->cod_id,
                'rank' => $entry->rank,
                'team_label' => $entry->team_label,
                'seat_number' => $entry->seat_number,
                'kills' => $entry->kills,
                'prize_amount' => (float) $entry->prize_amount,
            ])->values(),
        ];
    }
}
