<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamInviteResource;
use App\Jobs\SendUserNotificationJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Services\JalaliService;
use App\Services\TeamInviteService;
use App\Services\TeamReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamInviteController extends BaseApiController
{
    public function __construct(protected TeamInviteService $teamInvites)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $pendingTeamInvites = $this->teamInvites->pendingForUser($userId);
        $sentTeamInvites = $this->teamInvites->sentForUser($userId);

        return $this->success([
            'pending' => TeamInviteResource::collection($pendingTeamInvites),
            'sent' => TeamInviteResource::collection($sentTeamInvites),
        ]);
    }

    public function store(Request $request, Tournament $tournament, TeamReservationService $service): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'teammate_cod_id' => 'required|string|max:100',
            'accept_rules' => 'required|accepted',
        ], [
            'teammate_cod_id.required' => 'آیدی کالاف هم‌تیمی الزامی است.',
            'accept_rules.accepted' => 'برای ثبت‌نام باید قوانین را بپذیرید.',
        ]);

        if ($tournament->seatMode() < 2) {
            return $this->error('رزرو تیمی فقط برای مسابقات دو نفره فعال است.', 422);
        }

        if (! $tournament->acceptsRegistration()) {
            return $this->error('ثبت‌نام این مسابقه بسته شده است.', 422);
        }

        if ($user->wallet < $tournament->entry_fee) {
            return $this->error('برای ارسال درخواست تیمی، موجودی کیف پول شما باید حداقل برابر هزینه ورودی باشد.', 422);
        }

        if (! $service->hasAvailableTeamSlot($tournament)) {
            return $this->error('جایگاه تیمی خالی برای این مسابقه وجود ندارد.', 422);
        }

        $codId = trim($request->teammate_cod_id);
        $invitee = User::where('cod_id', $codId)->first();

        if (! $invitee) {
            return $this->error('کاربری با این آیدی کالاف یافت نشد.', 422);
        }

        $userId = (int) $user->id;
        $inviteeId = (int) $invitee->id;

        if ($inviteeId === $userId) {
            return $this->error('نمی‌توانید خودتان را به عنوان هم‌تیمی انتخاب کنید.', 422);
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
            return $this->error('یکی از شما قبلاً در این مسابقه ثبت‌نام کرده است.', 422);
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
            return $this->error('درخواست تیمی فعالی برای این مسابقه وجود دارد.', 422);
        }

        try {
            Registration::create([
                'user_id' => $user->id,
                'tournament_id' => $tournament->id,
                'status' => 'waiting',
                'reservation_type' => 'team',
            ]);

            $invite = TeamInvite::create([
                'tournament_id' => $tournament->id,
                'inviter_id' => $userId,
                'invitee_id' => $inviteeId,
                'status' => TeamInvite::STATUS_PENDING,
            ]);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('ارسال درخواست رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
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

        $invite->load(['tournament', 'inviter', 'invitee']);

        return $this->success(
            new TeamInviteResource($invite),
            "درخواست رزرو تیمی برای «{$invitee->cod_id}» ارسال شد. پس از تأیید هم‌تیمی، جایگاه تیمی رزرو می‌شود.",
            201
        );
    }

    public function accept(Request $request, TeamInvite $invite, TeamReservationService $service): JsonResponse
    {
        $user = $request->user();

        if ((int) $invite->invitee_id !== (int) $user->id) {
            return $this->error('دسترسی غیرمجاز.', 403);
        }

        try {
            $result = $service->accept($invite);
        } catch (\Throwable $e) {
            report($e);

            return $this->error('تأیید رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        if (! $result['ok']) {
            return $this->error($result['message'], 422);
        }

        $invite->refresh()->load(['tournament', 'inviter', 'invitee']);

        return $this->success(new TeamInviteResource($invite), $result['message']);
    }

    public function decline(Request $request, TeamInvite $invite): JsonResponse
    {
        $user = $request->user();

        if ((int) $invite->invitee_id !== (int) $user->id || ! $invite->isPending()) {
            return $this->error('دسترسی غیرمجاز.', 403);
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

            return $this->error('رد درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        return $this->success(null, 'درخواست تیمی رد شد.');
    }

    public function cancel(Request $request, TeamInvite $invite): JsonResponse
    {
        $user = $request->user();

        if ((int) $invite->inviter_id !== (int) $user->id || ! $invite->isPending()) {
            return $this->error('دسترسی غیرمجاز.', 403);
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

            return $this->error('لغو درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        return $this->success(null, 'درخواست تیمی لغو شد.');
    }
}
