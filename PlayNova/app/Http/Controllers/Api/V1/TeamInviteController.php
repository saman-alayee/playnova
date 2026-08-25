<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamInviteResource;
use App\Jobs\ExpireTeamInviteJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TeamInviteService;
use App\Services\TeamReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        $requiredInvites = $tournament->requiredTeammateInvites();

        $rules = [
            'accept_rules' => 'required|accepted',
        ];

        if ($requiredInvites > 1) {
            $rules['teammate_cod_ids'] = "required|array|size:{$requiredInvites}";
            $rules['teammate_cod_ids.*'] = 'required|string|max:100|distinct';
        } else {
            $rules['teammate_cod_id'] = 'required|string|max:100';
        }

        $request->validate($rules, [
            'teammate_cod_id.required' => 'آیدی کالاف هم‌تیمی الزامی است.',
            'teammate_cod_ids.required' => 'آیدی کالاف هم‌تیمی‌ها الزامی است.',
            'teammate_cod_ids.size' => "برای این مسابقه {$requiredInvites} آیدی هم‌تیمی لازم است.",
            'teammate_cod_ids.*.distinct' => 'آیدی‌های هم‌تیمی نباید تکراری باشند.',
            'accept_rules.accepted' => 'برای ثبت‌نام باید قوانین را بپذیرید.',
        ]);

        if (! $tournament->supportsTeamInvite()) {
            return $this->error('رزرو تیمی فقط برای مسابقات چندنفره فعال است.', 422);
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

        $codIds = $requiredInvites > 1
            ? array_map('trim', $request->input('teammate_cod_ids', []))
            : [trim((string) $request->teammate_cod_id)];

        $userId = (int) $user->id;
        $inviteeIds = [];

        foreach ($codIds as $codId) {
            if ($codId === '') {
                return $this->error('آیدی کالاف هم‌تیمی نمی‌تواند خالی باشد.', 422);
            }

            $invitee = User::where('cod_id', $codId)->first();
            if (! $invitee) {
                return $this->error("کاربری با آیدی «{$codId}» یافت نشد.", 422);
            }

            if ((int) $invitee->id === $userId) {
                return $this->error('نمی‌توانید خودتان را به عنوان هم‌تیمی انتخاب کنید.', 422);
            }

            $inviteeIds[] = (int) $invitee->id;
        }

        if (count($inviteeIds) !== count(array_unique($inviteeIds))) {
            return $this->error('هم‌تیمی‌های انتخاب‌شده نباید تکراری باشند.', 422);
        }

        Registration::where('user_id', $userId)
            ->where('tournament_id', $tournament->id)
            ->whereNull('seat_number')
            ->where(function ($q) {
                $q->where('reservation_type', 'solo')->orWhereNull('reservation_type');
            })
            ->delete();

        $participantIds = array_merge([$userId], $inviteeIds);

        $existingReg = Registration::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $participantIds)
            ->where(function ($q) {
                $q->whereNotNull('seat_number')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'waiting')
                            ->where('reservation_type', 'team');
                    });
            })
            ->exists();

        if ($existingReg) {
            return $this->error('یکی از اعضای تیم قبلاً در این مسابقه ثبت‌نام کرده است.', 422);
        }

        $pendingInvite = TeamInvite::where('tournament_id', $tournament->id)
            ->where('status', TeamInvite::STATUS_PENDING)
            ->where(function ($q) use ($participantIds) {
                $q->whereIn('inviter_id', $participantIds)
                    ->orWhereIn('invitee_id', $participantIds);
            })
            ->exists();

        if ($pendingInvite) {
            return $this->error('درخواست تیمی فعالی برای این مسابقه وجود دارد.', 422);
        }

        try {
            $invites = DB::transaction(function () use ($user, $tournament, $userId, $inviteeIds) {
                Registration::create([
                    'user_id' => $user->id,
                    'tournament_id' => $tournament->id,
                    'status' => 'waiting',
                    'reservation_type' => 'team',
                ]);

                $teamGroupId = (string) Str::uuid();
                $expiresAt = now()->addSeconds(TeamInvite::INVITE_TTL_SECONDS);
                $created = [];

                foreach ($inviteeIds as $inviteeId) {
                    $created[] = TeamInvite::create([
                        'tournament_id' => $tournament->id,
                        'team_group_id' => count($inviteeIds) > 1 ? $teamGroupId : null,
                        'inviter_id' => $userId,
                        'invitee_id' => $inviteeId,
                        'status' => TeamInvite::STATUS_PENDING,
                        'expires_at' => $expiresAt,
                    ]);
                }

                return $created;
            });

            foreach ($invites as $invite) {
                ExpireTeamInviteJob::dispatch($invite->id)->delay(now()->addSeconds(TeamInvite::INVITE_TTL_SECONDS));
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->error('ارسال درخواست رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        foreach ($participantIds as $participantId) {
            $this->teamInvites->forgetForUser($participantId);
        }

        $firstInvite = $invites[0];
        $firstInvite->load(['tournament', 'inviter', 'invitee']);

        $message = count($invites) > 1
            ? 'درخواست‌های رزرو تیمی برای ' . count($invites) . ' هم‌تیمی ارسال شد. پس از تأیید همه، جایگاه تیمی رزرو می‌شود.'
            : "درخواست رزرو تیمی برای «{$firstInvite->invitee?->cod_id}» ارسال شد. پس از تأیید هم‌تیمی، جایگاه تیمی رزرو می‌شود.";

        return $this->success(
            TeamInviteResource::collection(collect($invites)->each->load(['tournament', 'inviter', 'invitee'])),
            $message,
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

        $this->forgetInviteCaches($invite);

        if (! $result['ok']) {
            return $this->error($result['message'], 422);
        }

        $invite->refresh()->load(['tournament', 'inviter', 'invitee']);

        return $this->success(new TeamInviteResource($invite), $result['message']);
    }

    public function decline(Request $request, TeamInvite $invite, TeamReservationService $service): JsonResponse
    {
        $user = $request->user();

        if ((int) $invite->invitee_id !== (int) $user->id || ! $invite->isPending()) {
            return $this->error('دسترسی غیرمجاز.', 403);
        }

        try {
            if ($invite->team_group_id) {
                $service->cancelGroup($invite->team_group_id, (int) $invite->inviter_id, (int) $invite->tournament_id);
                TeamInvite::query()
                    ->where('team_group_id', $invite->team_group_id)
                    ->where('status', TeamInvite::STATUS_PENDING)
                    ->update(['status' => TeamInvite::STATUS_DECLINED]);
            } else {
                $invite->update(['status' => TeamInvite::STATUS_DECLINED]);

                Registration::where('user_id', $invite->inviter_id)
                    ->where('tournament_id', $invite->tournament_id)
                    ->whereNull('seat_number')
                    ->delete();
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->error('رد درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        $this->forgetInviteCaches($invite);

        return $this->success(null, 'درخواست تیمی رد شد.');
    }

    public function cancel(Request $request, TeamInvite $invite, TeamReservationService $service): JsonResponse
    {
        $user = $request->user();

        if ((int) $invite->inviter_id !== (int) $user->id || ! $invite->isPending()) {
            return $this->error('دسترسی غیرمجاز.', 403);
        }

        try {
            if ($invite->team_group_id) {
                $service->cancelGroup($invite->team_group_id, (int) $user->id, (int) $invite->tournament_id);
                TeamInvite::query()
                    ->where('team_group_id', $invite->team_group_id)
                    ->where('status', TeamInvite::STATUS_PENDING)
                    ->update(['status' => TeamInvite::STATUS_CANCELLED]);
            } else {
                $invite->update(['status' => TeamInvite::STATUS_CANCELLED]);

                Registration::where('user_id', $user->id)
                    ->where('tournament_id', $invite->tournament_id)
                    ->whereNull('seat_number')
                    ->delete();
            }
        } catch (\Throwable $e) {
            report($e);

            return $this->error('لغو درخواست تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        $this->forgetInviteCaches($invite);

        return $this->success(null, 'درخواست تیمی لغو شد.');
    }

    protected function forgetInviteCaches(TeamInvite $invite): void
    {
        $this->teamInvites->forgetForUser((int) $invite->inviter_id);
        $this->teamInvites->forgetForUser((int) $invite->invitee_id);

        if ($invite->team_group_id) {
            $relatedIds = TeamInvite::query()
                ->where('team_group_id', $invite->team_group_id)
                ->pluck('invitee_id')
                ->merge(TeamInvite::query()->where('team_group_id', $invite->team_group_id)->pluck('inviter_id'))
                ->unique();

            foreach ($relatedIds as $relatedId) {
                $this->teamInvites->forgetForUser((int) $relatedId);
            }
        }
    }
}
