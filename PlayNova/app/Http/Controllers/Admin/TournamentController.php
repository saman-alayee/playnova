<?php

namespace App\Http\Controllers\Admin;

use App\Http\Traits\InvalidatesAdminDashboard;
use App\Models\Registration;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TournamentController extends BaseAdminController
{
    use InvalidatesAdminDashboard;

    public function tournaments()
    {
        $tournaments = Tournament::with('winner')->orderByDesc('start_date')->paginate(30);
        return view('admin.tournaments', compact('tournaments'));
    }

    public function storeTournament(Request $request)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game' => 'nullable|string|max:255',
            'league' => 'nullable|in:beginner,intermediate,professional',
            'description' => 'nullable|string',
            'entry_fee' => 'required|numeric|min:0|max:999999999999',
            'prize_pool' => 'required|numeric|min:0|max:999999999999',
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
            'description' => $validated['description'],
            'entry_fee' => $validated['entry_fee'],
            'prize_pool' => $validated['prize_pool'],
            'capacity' => $validated['capacity'],
            'seat_mode' => (int) $validated['seat_mode'],
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'game_login_info' => $validated['game_login_info'] ?? null,
            'status' => 'upcoming',
            'registered_count' => 0,
        ]);

        $this->invalidateAdminDashboard();

        return redirect()->route('admin.tournaments')->with('success', 'مسابقه با موفقیت ایجاد شد.');
    }

    public function editTournamentForm($id)
    {
        $tournament = Tournament::findOrFail($id);
        $registeredUsers = Registration::where('tournament_id', $id)
            ->whereHas('user')
            ->with('user')
            ->get()
            ->pluck('user')
            ->filter()
            ->values();

        return view('admin.tournaments-edit', compact('tournament', 'registeredUsers'));
    }

    public function updateTournament(Request $request, $id)
    {
        $tournament = Tournament::findOrFail($id);
        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'game' => 'nullable|string|max:255',
            'league' => 'nullable|in:beginner,intermediate,professional',
            'description' => 'nullable|string',
            'entry_fee' => 'required|numeric|min:0|max:999999999999',
            'prize_pool' => 'required|numeric|min:0|max:999999999999',
            'capacity' => 'required|integer|min:1|max:10000',
            'seat_mode' => 'required|in:1,2,4',
            'start_date' => 'required|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'status' => 'required|in:upcoming,active,ongoing,ended,cancelled',
            'winner_id' => 'nullable|exists:users,id',
            'game_login_info' => 'nullable|string|max:5000',
        ]);
        $tournament->update($validated);

        $this->invalidateAdminDashboard();

        return redirect()->route('admin.tournaments')->with('success', 'مسابقه با موفقیت به‌روزرسانی شد.');
    }

    public function deleteTournament($id)
    {
        $tournament = Tournament::findOrFail($id);
        $tournament->delete();

        $this->invalidateAdminDashboard();

        return back()->with('success', 'مسابقه با موفقیت حذف شد.');
    }

    public function recordResult(Request $request, Tournament $tournament)
    {
        $request->validate([
            'winner_id' => 'required|exists:users,id',
        ]);

        $isRegistered = Registration::where('tournament_id', $tournament->id)
            ->where('user_id', $request->winner_id)
            ->exists();

        if (!$isRegistered) {
            return back()->with('error', 'کاربر انتخاب‌شده در این مسابقه ثبت‌نام نکرده است.');
        }

        DB::transaction(function () use ($request, $tournament) {
            $winner = User::findOrFail($request->winner_id);
            $tournament->update([
                'status' => 'ended',
                'winner_id' => $winner->id,
            ]);
            $winner->increment('wins');
            $winner->creditWallet(
                (float) $tournament->prize_pool,
                'prize',
                'جایزه مسابقه: ' . $tournament->title,
                'prize_' . $tournament->id
            );

            $participantIds = Registration::where('tournament_id', $tournament->id)
                ->where('status', 'registered')
                ->pluck('user_id');
            User::whereIn('id', $participantIds)
                ->where('id', '!=', $winner->id)
                ->increment('losses');
            Registration::where('tournament_id', $tournament->id)
                ->where('status', 'registered')
                ->update(['status' => 'confirmed']);
        });

        $this->invalidateAdminDashboard();

        return back()->with('success', 'نتیجه مسابقه با موفقیت ثبت شد. برنده: ' . User::find($request->winner_id)->username);
    }

    public function updateTournamentStatus(Request $request, Tournament $tournament)
    {
        $request->validate([
            'status' => 'required|in:upcoming,active,ongoing,ended,cancelled',
        ]);

        $tournament->update(['status' => $request->status]);

        $this->invalidateAdminDashboard();

        return back()->with('success', 'وضعیت مسابقه با موفقیت تغییر کرد.');
    }

    public function payTournamentPrize(Tournament $tournament)
    {
        if (!$tournament->winner_id) {
            return back()->with('error', 'برنده این مسابقه مشخص نشده است.');
        }

        $referenceId = 'prize_' . $tournament->id;
        $alreadyPaid = Transaction::where('type', 'prize')
            ->where('reference_id', $referenceId)
            ->where('status', 'completed')
            ->exists();

        if ($alreadyPaid) {
            return back()->with('error', 'جایزه این مسابقه قبلاً واریز شده است.');
        }

        $winner = User::findOrFail($tournament->winner_id);

        DB::transaction(function () use ($winner, $tournament, $referenceId) {
            $winner->creditWallet(
                (float) $tournament->prize_pool,
                'prize',
                'جایزه مسابقه: ' . $tournament->title,
                $referenceId
            );
        });

        $this->invalidateAdminDashboard();

        return back()->with('success', 'جایزه ' . number_format($tournament->prize_pool) . ' تومان به کیف پول ' . $winner->username . ' واریز شد.');
    }
}
