<?php

namespace App\Http\Controllers;

use App\Jobs\SendUserNotificationJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Services\JalaliService;
use App\Services\TeamInviteService;
use App\Services\TeamReservationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class TeamInviteController extends Controller
{
    public function __construct(protected TeamInviteService $teamInvites) {}

    protected function inviteSignature($pending, $sent): string
    {
        return $pending->pluck('id')->join(',') . '|' . $sent->pluck('id')->join(',');
    }

    public function banner()
    {
        $userId = (int) Auth::id();
        $pendingTeamInvites = $this->teamInvites->pendingForUser($userId);
        $sentTeamInvites = $this->teamInvites->sentForUser($userId);

        return response()->json([
            'html' => view('components.team-invite-banner-content', compact('pendingTeamInvites', 'sentTeamInvites'))->render(),
            'signature' => $this->inviteSignature($pendingTeamInvites, $sentTeamInvites),
        ]);
    }

    protected function inviteActionResponse(Request $request, bool $ok, string $message, string $type = 'success')
    {
        if ($request->expectsJson()) {
            return response()->json([
                'ok' => $ok,
                'message' => $message,
                'type' => $type,
            ], $ok ? 200 : 422);
        }

        $flashKey = match ($type) {
            'error' => 'error',
            'info' => 'info',
            default => 'success',
        };

        return redirect()->route('home')->with($flashKey, $message);
    }

    public function store(Request $request, Tournament $tournament, TeamReservationService $service)
    {
        $user = Auth::user();

        $request->validate([
            'teammate_cod_id' => 'required|string|max:100',
            'accept_rules' => 'required|accepted',
        ], [
            'teammate_cod_id.required' => 'آیدی کالاف هم‌تیمی الزامی است.',
            'accept_rules.accepted' => 'برای ثبت‌نام باید قوانین را بپذیرید.',
        ]);

        if ($tournament->seatMode() < 2) {
            return back()->with('error', 'رزرو تیمی فقط برای مسابقات دو نفره فعال است.');
        }

        if (! $tournament->acceptsRegistration()) {
            return back()->with('error', 'ثبت‌نام این مسابقه بسته شده است.');
        }

        if ($user->wallet < $tournament->entry_fee) {
            return back()->with('error', 'برای ارسال درخواست تیمی، موجودی کیف پول شما باید حداقل برابر هزینه ورودی باشد.');
        }

        if (! $service->hasAvailableTeamSlot($tournament)) {
            return back()->with('error', 'جایگاه تیمی خالی برای این مسابقه وجود ندارد.');
        }

        $codId = trim($request->teammate_cod_id);
        $invitee = User::where('cod_id', $codId)->first();

        if (! $invitee) {
            return back()->with('error', 'کاربری با این آیدی کالاف یافت نشد.');
        }

        $userId = (int) $user->id;
        $inviteeId = (int) $invitee->id;

        if ($inviteeId === $userId) {
            return back()->with('error', 'نمی‌توانید خودتان را به عنوان هم‌تیمی انتخاب کنید.');
        }

        Registration::where('user_id', $userId)
            ->where('tournament_id', $tournament->id)
            ->whereNull('seat_number')
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->where('reservation_type', 'solo')->orWhereNull('reservation_type');
            })
            ->delete();

        $existingReg = Registration::where('tournament_id', $tournament->id)
            ->whereIn('user_id', [$userId, $inviteeId])
            ->where(function ($q) {
                $q->whereNotNull('seat_number')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'waiting')
                            ->where('reservation_type', 'team');
                    });
            })
            ->exists();

        if ($existingReg) {
            return back()->with('error', 'یکی از شما قبلاً در این مسابقه ثبت‌نام کرده است.');
        }

        $pendingInvite = TeamInvite::where('tournament_id', $tournament->id)
            ->where('status', TeamInvite::STATUS_PENDING)
            ->where(function ($q) use ($userId, $inviteeId) {
                $q->where('inviter_id', $userId)
                    ->orWhere('invitee_id', $userId)
                    ->orWhere('inviter_id', $inviteeId)
                    ->orWhere('invitee_id', $inviteeId);
            })
            ->exists();

        if ($pendingInvite) {
            return back()->with('error', 'درخواست تیمی فعالی برای این مسابقه وجود دارد.');
        }

        try {
            Registration::create([
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'status' => 'waiting',
                'reservation_type' => 'team',
            ]);

            TeamInvite::create([
                'tournament_id' => $tournament->id,
                'inviter_id' => $userId,
                'invitee_id' => $inviteeId,
                'status' => TeamInvite::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'ارسال درخواست رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.');
        }

        $startTime = $tournament->start_date
            ? JalaliService::formatTime($tournament->start_date)
            : 'زمان اعلام‌شده';

        SendUserNotificationJob::dispatch(
            $inviteeId,
            'درخواست رزرو تیمی',
            "{$user->username} ({$user->cod_id}) از شما برای شرکت در «{$tournament->title}» در ساعت {$startTime} با هزینه ورودی " . number_format($tournament->entry_fee) . " تومان درخواست داده است.",
            'team_invite'
        );

        $this->teamInvites->forgetForUser($userId);
        $this->teamInvites->forgetForUser($inviteeId);

        return redirect()->route('home')
            ->with('success', "درخواست رزرو تیمی برای «{$invitee->cod_id}» ارسال شد. پس از تأیید هم‌تیمی، جایگاه تیمی رزرو می‌شود.");
    }

    public function accept(Request $request, TeamInvite $invite, TeamReservationService $service)
    {
        $user = Auth::user();

        if ((int) $invite->invitee_id !== (int) $user->id) {
            abort(403);
        }

        try {
            $result = $service->accept($invite);
        } catch (\Throwable $e) {
            report($e);

            return $this->inviteActionResponse($request, false, 'تأیید رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 'error');
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        return $this->inviteActionResponse(
            $request,
            $result['ok'],
            $result['message'],
            $result['ok'] ? 'success' : 'error'
        );
    }

    public function decline(Request $request, TeamInvite $invite)
    {
        $user = Auth::user();

        if ((int) $invite->invitee_id !== (int) $user->id || ! $invite->isPending()) {
            abort(403);
        }

        try {
            $invite->update(['status' => TeamInvite::STATUS_DECLINED]);

            Registration::where('user_id', $invite->inviter_id)
                ->where('tournament_id', $invite->tournament_id)
                ->whereNull('seat_number')
                ->delete();

            $tournament = $invite->tournament;
            SendUserNotificationJob::dispatch(
                $invite->inviter_id,
                'رد درخواست تیمی',
                "{$user->username} درخواست رزرو تیمی شما برای «{$tournament->title}» را رد کرد.",
                'team_invite_declined'
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->inviteActionResponse($request, false, 'رد درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 'error');
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        return $this->inviteActionResponse($request, true, 'درخواست تیمی رد شد.', 'info');
    }

    public function cancel(Request $request, TeamInvite $invite)
    {
        $user = Auth::user();

        if ((int) $invite->inviter_id !== (int) $user->id || ! $invite->isPending()) {
            abort(403);
        }

        try {
            $invite->update(['status' => TeamInvite::STATUS_CANCELLED]);

            Registration::where('user_id', $user->id)
                ->where('tournament_id', $invite->tournament_id)
                ->whereNull('seat_number')
                ->delete();

            SendUserNotificationJob::dispatch(
                $invite->invitee_id,
                'لغو درخواست تیمی',
                "{$user->username} درخواست رزرو تیمی برای «{$invite->tournament->title}» را لغو کرد.",
                'team_invite_cancelled'
            );
        } catch (\Throwable $e) {
            report($e);

            return $this->inviteActionResponse($request, false, 'لغو درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 'error');
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        return $this->inviteActionResponse($request, true, 'درخواست تیمی لغو شد.', 'info');
    }
}
