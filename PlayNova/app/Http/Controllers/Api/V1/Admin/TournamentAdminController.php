<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseApiController;
use App\Http\Controllers\Api\V1\Admin\Concerns\AuthorizesAdmin;
use App\Http\Resources\V1\TournamentResource;
use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Modules\Tournament\Services\TournamentPrizeService;
use App\Modules\Tournament\Services\TournamentRegistrationGuard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TournamentAdminController extends BaseApiController
{
    use AuthorizesAdmin;
    use InvalidatesAdminDashboard;

    public function show(Tournament $tournament): JsonResponse
    {
        $this->authorizeAdmin();
        $tournament->load('winner');

        return $this->success(array_merge(
            TournamentResource::make($tournament)->resolve(),
            [
                'game_login_info' => $tournament->game_login_info,
                'winner_id' => $tournament->winner_id,
            ]
        ));
    }

    public function participants(Tournament $tournament): JsonResponse
    {
        $this->authorizeAdmin();

        $participants = Registration::where('tournament_id', $tournament->id)
            ->whereIn('status', ['registered', 'confirmed'])
            ->with('user:id,username,email,cod_id')
            ->get()
            ->filter(fn ($r) => $r->user !== null)
            ->map(fn ($r) => [
                'user_id' => $r->user_id,
                'username' => $r->user->username,
                'email' => $r->user->email,
                'cod_id' => $r->user->cod_id,
                'seat_number' => $r->seat_number,
            ])
            ->values();

        return $this->success($participants);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game' => 'nullable|string|max:255',
            'league' => 'nullable|in:beginner,intermediate,professional',
            'description' => 'nullable|string',
            'entry_fee' => 'required|numeric|min:0|max:999999999999',
            'prize_pool' => 'required|numeric|min:0|max:999999999999',
            'prize_ranks' => 'nullable|array',
            'capacity' => 'required|integer|min:1|max:10000',
            'seat_mode' => 'required|in:1,2,4',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'game_login_info' => 'nullable|string|max:5000',
        ]);

        $tournament = Tournament::create([
            'title' => $validated['title'],
            'game' => $validated['game'] ?? 'Call of Duty Mobile',
            'league' => $validated['league'] ?? 'intermediate',
            'description' => $validated['description'] ?? null,
            'entry_fee' => $validated['entry_fee'],
            'prize_pool' => $validated['prize_pool'],
            'capacity' => $validated['capacity'],
            'seat_mode' => (int) $validated['seat_mode'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'game_login_info' => $validated['game_login_info'] ?? null,
            'status' => 'upcoming',
            'registered_count' => 0,
            'prize_ranks' => $this->prizeRanksFromRequest($request, (float) $validated['prize_pool']),
        ]);

        TournamentListingService::forgetHomeCache();
        $this->invalidateAdminDashboard();

        return $this->success(TournamentResource::make($tournament), 'مسابقه با موفقیت ایجاد شد.', 201);
    }

    public function update(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeAdmin();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game' => 'nullable|string|max:255',
            'league' => 'nullable|in:beginner,intermediate,professional',
            'description' => 'nullable|string',
            'entry_fee' => 'required|numeric|min:0|max:999999999999',
            'prize_pool' => 'required|numeric|min:0|max:999999999999',
            'prize_ranks' => 'nullable|array',
            'capacity' => 'required|integer|min:1|max:10000',
            'seat_mode' => 'required|in:1,2,4',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:upcoming,active,ongoing,ended,cancelled',
            'winner_id' => 'nullable|exists:users,id',
            'game_login_info' => 'nullable|string|max:5000',
        ]);

        if ($request->exists('prize_ranks')) {
            $validated['prize_ranks'] = $this->prizeRanksFromRequest($request, (float) $validated['prize_pool']);
        }

        $tournament->update($validated);

        app(TournamentRegistrationGuard::class)->closeOpenRegistrations($tournament->fresh());

        TournamentListingService::forgetHomeCache();
        $this->invalidateAdminDashboard();

        return $this->success(TournamentResource::make($tournament->fresh('winner')), 'مسابقه به‌روزرسانی شد.');
    }

    public function destroy(Tournament $tournament): JsonResponse
    {
        $this->authorizeAdmin();
        $tournament->delete();

        TournamentListingService::forgetHomeCache();
        $this->invalidateAdminDashboard();

        return $this->success(null, 'مسابقه حذف شد.');
    }

    public function updateStatus(Request $request, Tournament $tournament): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'status' => 'required|in:upcoming,active,ongoing,ended,cancelled',
        ]);

        $tournament->update(['status' => $request->status]);

        app(TournamentRegistrationGuard::class)->closeOpenRegistrations($tournament->fresh());

        TournamentListingService::forgetHomeCache();
        $this->invalidateAdminDashboard();

        return $this->success(TournamentResource::make($tournament->fresh()), 'وضعیت مسابقه تغییر کرد.');
    }

    public function recordResult(Request $request, Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        $request->validate([
            'winner_id' => 'required|exists:users,id',
        ]);

        $isRegistered = Registration::where('tournament_id', $tournament->id)
            ->where('user_id', $request->winner_id)
            ->whereNotNull('seat_number')
            ->exists();

        if (! $isRegistered) {
            return $this->error('کاربر انتخاب‌شده در این مسابقه ثبت‌نام نکرده است.');
        }

        DB::transaction(function () use ($request, $tournament, $prizes) {
            $winner = User::findOrFail($request->winner_id);

            $tournament->update([
                'status' => 'ended',
                'winner_id' => $winner->id,
            ]);
            $winner->increment('wins');

            $loserIds = Registration::where('tournament_id', $tournament->id)
                ->whereNotNull('seat_number')
                ->where('user_id', '!=', $winner->id)
                ->pluck('user_id');

            if ($loserIds->isNotEmpty()) {
                User::whereIn('id', $loserIds)->increment('losses');
            }

            $prizes->submitPendingBatch($tournament, (int) $winner->id);
        });

        TournamentListingService::forgetHomeCache();
        TournamentListingService::forgetLeaderboardCache();
        $this->invalidateAdminDashboard();

        $winner = User::find($request->winner_id);

        return $this->success(null, 'نتیجه ثبت شد. جوایز در انتظار تأیید ادمین است. برنده: ' . $winner->username);
    }

    public function payPrize(Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        return $this->error('واریز مستقیم جایزه غیرفعال است. از بخش تأیید جوایز استفاده کنید.', 422);
    }

    public function prizeStatus(Tournament $tournament, TournamentPrizeService $prizes): JsonResponse
    {
        $this->authorizeAdmin();

        $batch = $prizes->findForTournament($tournament);

        return $this->success([
            'prize_paid' => $batch?->isPaid() ?? false,
            'prize_pending' => $batch?->isPending() ?? false,
            'batch_status' => $batch?->status,
        ]);
    }

    /**
     * @return array<int, float>
     */
    protected function prizeRanksFromRequest(Request $request, float $prizePool): array
    {
        $table = Tournament::normalizePrizeRanks($request->input('prize_ranks'));
        $sum = array_sum($table);

        if ($table !== [] && abs($sum - $prizePool) > 0.5) {
            throw ValidationException::withMessages([
                'prize_ranks' => 'مجموع جوایز باید برابر بودجه مسابقه (' . number_format($prizePool) . ' تومان) باشد. مجموع فعلی: ' . number_format($sum) . ' تومان.',
            ]);
        }

        return $table;
    }
}
