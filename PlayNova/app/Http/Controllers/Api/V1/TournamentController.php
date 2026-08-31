<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\V1\NewsResource;
use App\Http\Resources\V1\RegistrationResource;
use App\Http\Resources\V1\RuleResource;
use App\Http\Resources\V1\TournamentResource;
use App\Http\Resources\V1\UserResource;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Content\Services\ContentCacheService;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Modules\Tournament\Services\TournamentRegistrationGuard;
use App\Modules\Tournament\Services\TournamentRegistrationService;
use App\Services\TournamentEntryFeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TournamentController extends BaseApiController
{
    public function home(TournamentListingService $listing): JsonResponse
    {
        $payload = $listing->homePayload();

        if (Auth::check()) {
            $this->attachUserRegistrationFlags(Auth::id(), [
                $payload['activeTournaments'],
                $payload['leagues']['beginner'],
                $payload['leagues']['intermediate'],
                $payload['leagues']['professional'],
            ]);
        }

        return $this->success([
            'active_tournaments' => TournamentResource::collection($payload['activeTournaments']),
            'leagues' => [
                'beginner' => TournamentResource::collection($payload['leagues']['beginner']),
                'intermediate' => TournamentResource::collection($payload['leagues']['intermediate']),
                'professional' => TournamentResource::collection($payload['leagues']['professional']),
            ],
            'news' => NewsResource::collection($payload['news']),
        ]);
    }

    /**
     * Attach per-user registration flags after cache so the shared home payload stays anonymous.
     * Logic mirrors resources/views/components/tournament-actions.blade.php.
     *
     * @param  array<int, Collection<int, Tournament>>  $collections
     */
    protected function attachUserRegistrationFlags(int $userId, array $collections): void
    {
        $tournamentIds = collect($collections)
            ->flatMap(fn (Collection $items) => $items->pluck('id'))
            ->unique()
            ->values();

        if ($tournamentIds->isEmpty()) {
            return;
        }

        $registeredIds = Registration::query()
            ->where('user_id', $userId)
            ->whereIn('tournament_id', $tournamentIds)
            ->whereNotNull('seat_number')
            ->pluck('tournament_id')
            ->flip();

        $pendingSeatIds = Registration::query()
            ->where('user_id', $userId)
            ->whereIn('tournament_id', $tournamentIds)
            ->whereNull('seat_number')
            ->where('status', 'waiting')
            ->where(function ($q) {
                $q->where('reservation_type', 'solo')->orWhereNull('reservation_type');
            })
            ->pluck('tournament_id')
            ->flip();

        $pendingTeamIds = TeamInvite::query()
            ->where('inviter_id', $userId)
            ->whereIn('tournament_id', $tournamentIds)
            ->where('status', TeamInvite::STATUS_PENDING)
            ->pluck('tournament_id')
            ->flip();

        foreach ($collections as $items) {
            foreach ($items as $tournament) {
                $tournament->setAttribute('is_registered', $registeredIds->has($tournament->id));
                $tournament->setAttribute('pending_seat', $pendingSeatIds->has($tournament->id));
                $tournament->setAttribute('pending_team', $pendingTeamIds->has($tournament->id));
            }
        }
    }

    public function show(Tournament $tournament): JsonResponse
    {
        $registration = null;
        $isRegistered = false;
        $pendingSeat = false;

        if (Auth::check()) {
            $registration = Registration::where('user_id', Auth::id())
                ->where('tournament_id', $tournament->id)
                ->first();

            if ($registration) {
                $pendingSeat = $registration->seat_number === null;
                $isRegistered = ! $pendingSeat;
            }
        }

        $players = $tournament->registrations()
            ->whereNotNull('seat_number')
            ->with('user')
            ->get();

        $occupiedSeats = $players->mapWithKeys(function ($reg) use ($tournament) {
            return [
                $reg->seat_number => [
                    'seat_number' => $reg->seat_number,
                    'seat_label' => $tournament->seatDisplayLabel((int) $reg->seat_number),
                    'user' => new UserResource($reg->user),
                ],
            ];
        });

        return $this->success([
            'tournament' => new TournamentResource($tournament),
            'is_registered' => $isRegistered,
            'pending_seat' => $pendingSeat,
            'registration' => $registration ? new RegistrationResource($registration) : null,
            'occupied_seats' => $occupiedSeats,
        ]);
    }

    public function register(Request $request, Tournament $tournament, TournamentRegistrationService $registrations, TournamentRegistrationGuard $guard): JsonResponse
    {
        $user = $request->user();

        try {
            $guard->assertCanRegister($tournament);
            $registration = $registrations->createPendingIntent($user, $tournament);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'registration_closed' => $this->error('ثبت‌نام این مسابقه بسته شده است.', 422),
                'tournament_full' => $this->error('ظرفیت مسابقه تکمیل شده است.', 422),
                'already_registered' => $this->error('شما قبلاً در این مسابقه ثبت‌نام کرده‌اید.', 422),
                'insufficient_wallet' => $this->error('موجودی کیف پول کافی نیست.', 422),
                default => $this->error('ثبت‌نام ناموفق بود.', 422),
            };
        } catch (\Throwable $e) {
            report($e);

            return $this->error('ثبت‌نام ناموفق بود. لطفاً دوباره تلاش کنید.', 500);
        }

        return $this->success([
            'registration' => new RegistrationResource($registration),
            'next_step' => 'select_seat',
        ], 'برای تکمیل ثبت‌نام، جایگاه خود را انتخاب و تأیید کنید.', 201);
    }

    public function selectSeat(Tournament $tournament, TournamentRegistrationGuard $guard): JsonResponse
    {
        $user = Auth::user();

        try {
            $guard->assertCanRegister($tournament);
        } catch (\RuntimeException) {
            return $this->error('ثبت‌نام این مسابقه بسته شده است.', 422);
        }

        $registration = Registration::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->first();

        if (! $registration) {
            return $this->error('ابتدا در این مسابقه ثبت‌نام کنید.', 422);
        }

        if ($registration->seat_number === null && ($registration->reservation_type ?? 'solo') === 'team') {
            return $this->error('درخواست رزرو تیمی شما در انتظار تأیید هم‌تیمی است.', 422);
        }

        if ($registration->seat_number !== null) {
            return $this->success([
                'registration' => new RegistrationResource($registration->load('tournament')),
                'seat_label' => $tournament->seatDisplayLabel((int) $registration->seat_number),
            ], 'جایگاه شما قبلاً ثبت شده است.');
        }

        $occupiedSeats = Registration::where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->with('user')
            ->get()
            ->mapWithKeys(function ($reg) use ($tournament) {
                return [
                    $reg->seat_number => [
                        'seat_number' => $reg->seat_number,
                        'seat_label' => $tournament->seatDisplayLabel((int) $reg->seat_number),
                        'user' => new UserResource($reg->user),
                    ],
                ];
            });

        return $this->success([
            'tournament' => new TournamentResource($tournament),
            'registration' => new RegistrationResource($registration),
            'teams_grid' => $tournament->teamsForGrid(),
            'occupied_seats' => $occupiedSeats,
        ]);
    }

    public function storeSeat(Request $request, Tournament $tournament, TournamentRegistrationService $registrations, TournamentRegistrationGuard $guard): JsonResponse
    {
        $request->validate([
            'seat_number' => 'required|integer|min:1|max:' . max(1, (int) $tournament->capacity),
        ]);

        $user = $request->user();
        $seatNumber = (int) $request->seat_number;

        try {
            $guard->assertCanRegister($tournament);
            $registration = $registrations->confirmSoloSeat($user, $tournament, $seatNumber);
        } catch (\RuntimeException $e) {
            return match ($e->getMessage()) {
                'registration_closed' => $this->error('ثبت‌نام این مسابقه بسته شده است.', 422),
                'not_registered' => $this->error('ابتدا در این مسابقه ثبت‌نام کنید.', 422),
                'already_selected' => $this->success([
                    'seat_label' => $tournament->seatDisplayLabel($seatNumber),
                ], 'جایگاه شما قبلاً انتخاب شده است.'),
                'seat_taken' => $this->error('این جایگاه قبلاً گرفته شده. جایگاه دیگری انتخاب کنید.', 409),
                'insufficient_wallet' => $this->error('موجودی کیف پول کافی نیست.', 422),
                'tournament_full' => $this->error('ظرفیت مسابقه تکمیل شده است.', 422),
                default => $this->error('انتخاب جایگاه ناموفق بود.', 422),
            };
        }

        TournamentListingService::forgetHomeCache();

        return $this->success([
            'registration' => new RegistrationResource($registration->load('tournament')),
            'seat_label' => $tournament->seatDisplayLabel($seatNumber),
        ], 'ثبت‌نام شما با موفقیت تکمیل شد.');
    }

    public function gameLoginInfo(Tournament $tournament): JsonResponse
    {
        $user = Auth::user();

        $registration = Registration::where('user_id', $user->id)
            ->where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->first(['id', 'seat_number']);

        if (! $registration) {
            return $this->error('شما در این مسابقه ثبت‌نام نکرده‌اید.', 403);
        }

        if (! $tournament->allowsGameLogin()) {
            return $this->error('اطلاعات ورود برای این مسابقه در دسترس نیست.', 403);
        }

        return $this->success([
            'title' => $tournament->title,
            'content' => $tournament->gameLoginMessage(),
            'has_info' => $tournament->hasPublishedGameLogin(),
            'seat_number' => $registration->seat_number,
            'seat_label' => $registration->seat_number
                ? $tournament->seatDisplayLabel((int) $registration->seat_number)
                : null,
        ]);
    }

    public function cancelPending(Tournament $tournament, TournamentEntryFeeService $fees, TournamentRegistrationService $registrations): JsonResponse
    {
        $user = Auth::user();

        try {
            DB::transaction(function () use ($user, $tournament, $fees, $registrations) {
                TeamInvite::query()
                    ->where('inviter_id', $user->id)
                    ->where('tournament_id', $tournament->id)
                    ->where('status', TeamInvite::STATUS_PENDING)
                    ->update(['status' => TeamInvite::STATUS_CANCELLED]);

                $registration = Registration::where('user_id', $user->id)
                    ->where('tournament_id', $tournament->id)
                    ->whereNull('seat_number')
                    ->lockForUpdate()
                    ->first();

                if (! $registration) {
                    throw new \RuntimeException('no_pending');
                }

                if ($fees->refundIfPaid($user, $tournament)) {
                    $registrations->syncRegisteredCount($tournament);
                }

                $registration->delete();
            });
        } catch (\RuntimeException) {
            return $this->error('ثبت‌نام ناتمامی برای لغو یافت نشد.', 422);
        }

        TournamentListingService::forgetHomeCache();

        return $this->success(null, 'ثبت‌نام ناتمام لغو شد.');
    }

    public function leaderboard(TournamentListingService $listing): JsonResponse
    {
        return $this->success(UserResource::collection($listing->leaderboard()));
    }

    public function rules(ContentCacheService $content): JsonResponse
    {
        return $this->success(RuleResource::collection($content->rules()));
    }
}
