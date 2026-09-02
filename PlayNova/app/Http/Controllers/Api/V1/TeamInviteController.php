<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\TeamInviteResource;
use App\Jobs\ExpireTeamInviteJob;
use App\Jobs\SendUserNotificationJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Services\TeamInviteService;
use App\Services\TeamReservationService;
use App\Support\SeatAdvisoryLock;
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
            'seat_number' => 'required|integer|min:1|max:' . max(1, (int) $tournament->capacity),
        ];

        if ($requiredInvites > 1) {
            $rules['teammate_cod_ids'] = "required|array|size:{$requiredInvites}";
            $rules['teammate_cod_ids.*'] = 'required|string|max:100|distinct';
        } else {
            $rules['teammate_cod_id'] = 'required|string|max:100';
        }

        $request->validate($rules, [
            'seat_number.required' => 'انتخاب جایگاه تیم الزامی است.',
            'teammate_cod_id.required' => 'آیدی کالاف هم‌تیمی الزامی است.',
            'teammate_cod_ids.required' => 'آیدی کالاف هم‌تیمی‌ها الزامی است.',
            'teammate_cod_ids.size' => "برای این مسابقه {$requiredInvites} آیدی هم‌تیمی لازم است.",
            'teammate_cod_ids.*.distinct' => 'آیدی‌های هم‌تیمی نباید تکراری باشند.',
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

        $seatNumber = (int) $request->seat_number;
        $teamSeats = $service->teamSeatsFromAnchor($tournament, $seatNumber);

        if ($teamSeats === []) {
            return $this->error('جایگاه انتخاب‌شده معتبر نیست.', 422);
        }

        if (! $service->validateTeamSeatsAvailable($tournament, $teamSeats)) {
            return $this->error('تیم انتخاب‌شده دیگر خالی نیست. جایگاه دیگری انتخاب کنید.', 409);
        }

        $teamFirstSeat = $teamSeats[0];

        $codIds = $requiredInvites > 1
            ? array_map('trim', $request->input('teammate_cod_ids', []))
            : [trim((string) $request->teammate_cod_id)];

        $userId = (int) $user->id;
        $inviteeIds = [];

        $normalizedCodIds = array_values(array_filter(array_map('trim', $codIds), fn (string $codId) => $codId !== ''));

        if (count($normalizedCodIds) !== count($codIds)) {
            return $this->error('آیدی کالاف هم‌تیمی نمی‌تواند خالی باشد.', 422);
        }

        $inviteesByCodId = User::query()
            ->whereIn('cod_id', $normalizedCodIds)
            ->get()
            ->keyBy('cod_id');

        foreach ($normalizedCodIds as $codId) {
            $invitee = $inviteesByCodId->get($codId);
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

        $registration = Registration::where('user_id', $userId)
            ->where('tournament_id', $tournament->id)
            ->whereNull('seat_number')
            ->where('status', 'waiting')
            ->where('reservation_type', 'team')
            ->first();

        if (! $registration) {
            return $this->error('ابتدا نوع ثبت‌نام «رزرو تیمی» را انتخاب کنید.', 422);
        }

        $participantIds = array_merge([$userId], $inviteeIds);

        $existingReg = Registration::where('tournament_id', $tournament->id)
            ->whereIn('user_id', $participantIds)
            ->where(function ($q) use ($userId) {
                $q->whereNotNull('seat_number')
                    ->orWhere(function ($q2) use ($userId) {
                        $q2->where('status', 'waiting')
                            ->where('reservation_type', 'team')
                            ->where('user_id', '!=', $userId);
                    });
            })
            ->exists();

        if ($existingReg) {
            return $this->error('یکی از اعضای تیم قبلاً در این مسابقه ثبت‌نام کرده است.', 422);
        }

        TeamInvite::query()
            ->where('inviter_id', $userId)
            ->where('tournament_id', $tournament->id)
            ->where('status', TeamInvite::STATUS_PENDING)
            ->update(['status' => TeamInvite::STATUS_CANCELLED]);

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
            $invites = SeatAdvisoryLock::run($tournament->id, $teamSeats, function () use ($service, $tournament, $userId, $inviteeIds, $teamFirstSeat) {
                return DB::transaction(function () use ($service, $tournament, $userId, $inviteeIds, $teamFirstSeat) {
                    $freshTournament = Tournament::whereKey($tournament->id)->firstOrFail();
                    $teamSeats = $service->teamSeatsFromAnchor($freshTournament, $teamFirstSeat);

                    if ($teamSeats === [] || ! $service->validateTeamSeatsAvailable($freshTournament, $teamSeats)) {
                        throw new \RuntimeException('seat_taken');
                    }

                    $teamGroupId = (string) Str::uuid();
                    $expiresAt = now()->addSeconds(TeamInvite::INVITE_TTL_SECONDS);
                    $created = [];

                    foreach ($inviteeIds as $inviteeId) {
                        $created[] = TeamInvite::create([
                            'tournament_id' => $freshTournament->id,
                            'team_group_id' => count($inviteeIds) > 1 ? $teamGroupId : null,
                            'team_first_seat' => $teamFirstSeat,
                            'inviter_id' => $userId,
                            'invitee_id' => $inviteeId,
                            'status' => TeamInvite::STATUS_PENDING,
                            'expires_at' => $expiresAt,
                        ]);
                    }

                    return $created;
                });
            });

            foreach ($invites as $invite) {
                ExpireTeamInviteJob::dispatch($invite->id)->delay(now()->addSeconds(TeamInvite::INVITE_TTL_SECONDS));

                SendUserNotificationJob::dispatch(
                    (int) $invite->invitee_id,
                    'دعوت به تیم',
                    sprintf('شما به تیم «%s» در مسابقه «%s» دعوت شده‌اید.', $user->username, $tournament->title),
                    'team_invite',
                );
            }
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'seat_taken') {
                return $this->error('تیم انتخاب‌شده دیگر خالی نیست. جایگاه دیگری انتخاب کنید.', 409);
            }

            throw $e;
        } catch (\Throwable $e) {
            report($e);

            return $this->error('ارسال درخواست رزرو تیمی ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        foreach ($participantIds as $participantId) {
            $this->teamInvites->forgetForUser($participantId);
        }

        $firstInvite = $invites[0];
        $firstInvite->load(['tournament', 'inviter', 'invitee']);

        $teamLabel = $tournament->seatDisplayLabel($teamFirstSeat);
        $message = count($invites) > 1
            ? 'درخواست‌های رزرو تیمی برای ' . count($invites) . ' هم‌تیمی ارسال شد. تا تأیید همه، مبلغی کسر نمی‌شود و جایگاه اشغال نمی‌شود.'
            : "درخواست رزرو تیمی برای «{$firstInvite->invitee?->cod_id}» ارسال شد. تا تأیید هم‌تیمی، مبلغی کسر نمی‌شود و جایگاه {$teamLabel} اشغال نمی‌شود.";

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
        $invite->loadMissing('tournament');
        $tournamentTitle = $invite->tournament?->title ?? 'مسابقه';

        SendUserNotificationJob::dispatch(
            (int) $invite->inviter_id,
            'رد دعوت تیمی',
            sprintf('دعوت تیمی شما در مسابقه «%s» رد شد.', $tournamentTitle),
            'team_invite',
        );

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
        $invite->loadMissing('tournament');
        $tournamentTitle = $invite->tournament?->title ?? 'مسابقه';

        if ($invite->team_group_id) {
            $inviteeIds = TeamInvite::query()
                ->where('team_group_id', $invite->team_group_id)
                ->pluck('invitee_id')
                ->unique();

            foreach ($inviteeIds as $inviteeId) {
                SendUserNotificationJob::dispatch(
                    (int) $inviteeId,
                    'لغو دعوت تیمی',
                    sprintf('دعوت تیمی شما در مسابقه «%s» لغو شد.', $tournamentTitle),
                    'team_invite',
                );
            }
        } else {
            SendUserNotificationJob::dispatch(
                (int) $invite->invitee_id,
                'لغو دعوت تیمی',
                sprintf('دعوت تیمی شما در مسابقه «%s» لغو شد.', $tournamentTitle),
                'team_invite',
            );
        }

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
