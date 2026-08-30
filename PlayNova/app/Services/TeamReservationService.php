<?php

namespace App\Services;

use App\Jobs\SendUserNotificationJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\User;
use App\Modules\Audit\Services\ActivityLogService;
use App\Modules\Tournament\Services\TournamentListingService;
use App\Modules\Tournament\Services\TournamentRegistrationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamReservationService
{
    public function __construct(
        protected TournamentRegistrationService $registrations,
        protected ActivityLogService $activity,
    ) {
    }

    /** @return list<int>|null */
    public function findAvailableTeamSeats(Tournament $tournament): ?array
    {
        $mode = $tournament->seatMode();
        if ($mode < 2) {
            return null;
        }

        $occupied = Registration::where('tournament_id', $tournament->id)
            ->whereNotNull('seat_number')
            ->pluck('seat_number')
            ->flip()
            ->all();

        for ($team = 1; $team <= $tournament->teamCount(); $team++) {
            $seats = [];
            $teamFree = true;

            for ($slot = 1; $slot <= $mode; $slot++) {
                $seatNumber = ($team - 1) * $mode + $slot;
                if ($seatNumber > (int) $tournament->capacity) {
                    break;
                }
                if (isset($occupied[$seatNumber])) {
                    $teamFree = false;
                    break;
                }
                $seats[] = $seatNumber;
            }

            if ($teamFree && count($seats) === $mode) {
                return $seats;
            }
        }

        return null;
    }

    public function hasAvailableTeamSlot(Tournament $tournament): bool
    {
        return $this->findAvailableTeamSeats($tournament) !== null;
    }

    /** @return array{ok:bool, message:string} */
    public function accept(TeamInvite $invite): array
    {
        if (! $invite->isPending() && $invite->status !== TeamInvite::STATUS_ACCEPTED) {
            return ['ok' => false, 'message' => 'این درخواست دیگر فعال نیست.'];
        }

        $tournament = $invite->tournament ?? Tournament::find($invite->tournament_id);
        if (! $tournament) {
            return ['ok' => false, 'message' => 'مسابقه یافت نشد.'];
        }

        if ($tournament->seatMode() >= 4 && $invite->team_group_id) {
            return $this->acceptSquadMember($invite, $tournament);
        }

        return $this->acceptDuo($invite);
    }

    /** @return array{ok:bool, message:string} */
    protected function acceptDuo(TeamInvite $invite): array
    {
        if (! $invite->isPending()) {
            return ['ok' => false, 'message' => 'این درخواست دیگر فعال نیست.'];
        }

        try {
            return DB::transaction(function () use ($invite) {
                $lockedInvite = TeamInvite::where('id', $invite->id)->lockForUpdate()->first();
                if (! $lockedInvite || ! $lockedInvite->isPending()) {
                    throw new \RuntimeException('inactive');
                }

                $tournament = Tournament::where('id', $lockedInvite->tournament_id)->lockForUpdate()->first();
                if (! $tournament || ! $tournament->acceptsRegistration()) {
                    throw new \RuntimeException('closed');
                }

                $teamSeats = $this->findAvailableTeamSeats($tournament);
                if ($teamSeats === null || count($teamSeats) < 2) {
                    throw new \RuntimeException('no_team_slot');
                }

                [$seatInviter, $seatInvitee] = [$teamSeats[0], $teamSeats[1]];

                $inviter = User::where('id', $lockedInvite->inviter_id)->lockForUpdate()->first();
                $invitee = User::where('id', $lockedInvite->invitee_id)->lockForUpdate()->first();
                $fee = (float) $tournament->entry_fee;

                if ($inviter->wallet < $fee) {
                    throw new \RuntimeException('inviter_wallet');
                }

                if ($invitee->wallet < $fee) {
                    throw new \RuntimeException('invitee_wallet');
                }

                $inviterReg = Registration::where('user_id', $inviter->id)
                    ->where('tournament_id', $tournament->id)
                    ->lockForUpdate()
                    ->first();

                if (! $inviterReg || $inviterReg->seat_number !== null) {
                    throw new \RuntimeException('inviter_reg');
                }

                $inviteeExists = Registration::where('user_id', $invitee->id)
                    ->where('tournament_id', $tournament->id)
                    ->lockForUpdate()
                    ->exists();

                if ($inviteeExists) {
                    throw new \RuntimeException('invitee_reg');
                }

                foreach ([$seatInviter, $seatInvitee] as $seat) {
                    $taken = Registration::where('tournament_id', $tournament->id)
                        ->where('seat_number', $seat)
                        ->lockForUpdate()
                        ->exists();
                    if ($taken) {
                        throw new \RuntimeException('seat_taken');
                    }
                }

                $this->chargeEntryFee($inviter, $tournament);
                $this->chargeEntryFee($invitee, $tournament);

                $inviterReg->update([
                    'seat_number' => $seatInviter,
                    'status' => 'confirmed',
                    'reservation_type' => 'team',
                ]);

                Registration::create([
                    'user_id' => $invitee->id,
                    'tournament_id' => $tournament->id,
                    'seat_number' => $seatInvitee,
                    'status' => 'confirmed',
                    'reservation_type' => 'team',
                ]);

                $this->registrations->syncRegisteredCount($tournament);

                $lockedInvite->update([
                    'status' => TeamInvite::STATUS_ACCEPTED,
                    'seat_number_inviter' => $seatInviter,
                    'seat_number_invitee' => $seatInvitee,
                    'failure_reason' => null,
                ]);

                $this->logTeamJoined($inviter, $invitee, $tournament, $seatInviter, $seatInvitee);

                $labelInviter = $tournament->seatDisplayLabel($seatInviter);
                $labelInvitee = $tournament->seatDisplayLabel($seatInvitee);

                TournamentListingService::forgetHomeCache();

                SendUserNotificationJob::dispatch(
                    (int) $lockedInvite->inviter_id,
                    'تأیید دعوت تیمی',
                    sprintf('هم‌تیمی شما در مسابقه «%s» دعوت را پذیرفت.', $tournament->title),
                    'team_invite',
                );

                return [
                    'ok' => true,
                    'message' => "رزرو تیمی با موفقیت انجام شد. جایگاه‌های {$labelInviter} و {$labelInvitee} رزرو شد.",
                ];
            });
        } catch (\RuntimeException $e) {
            return $this->failInvite($invite, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->failInvite($invite, 'internal');
        }
    }

    /** @return array{ok:bool, message:string} */
    protected function acceptSquadMember(TeamInvite $invite, Tournament $tournament): array
    {
        try {
            return DB::transaction(function () use ($invite, $tournament) {
                $lockedInvite = TeamInvite::where('id', $invite->id)->lockForUpdate()->first();
                if (! $lockedInvite) {
                    throw new \RuntimeException('inactive');
                }

                if ($lockedInvite->isExpired()) {
                    throw new \RuntimeException('inactive');
                }

                if ($lockedInvite->status === TeamInvite::STATUS_ACCEPTED) {
                    return $this->maybeConfirmSquadGroup($lockedInvite, $tournament);
                }

                if (! $lockedInvite->isPending()) {
                    throw new \RuntimeException('inactive');
                }

                $lockedInvite->update(['status' => TeamInvite::STATUS_ACCEPTED]);

                return $this->maybeConfirmSquadGroup($lockedInvite->fresh(), $tournament);
            });
        } catch (\RuntimeException $e) {
            return $this->failSquadGroup($invite, $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return $this->failSquadGroup($invite, 'internal');
        }
    }

    /** @return array{ok:bool, message:string} */
    protected function maybeConfirmSquadGroup(TeamInvite $invite, Tournament $tournament): array
    {
        $groupId = $invite->team_group_id;
        if (! $groupId) {
            throw new \RuntimeException('inactive');
        }

        $groupInvites = TeamInvite::query()
            ->where('team_group_id', $groupId)
            ->lockForUpdate()
            ->get();

        $required = $tournament->requiredTeammateInvites();

        if ($groupInvites->count() !== $required) {
            throw new \RuntimeException('inactive');
        }

        if ($groupInvites->contains(fn (TeamInvite $i) => $i->isExpired() || in_array($i->status, [
            TeamInvite::STATUS_DECLINED,
            TeamInvite::STATUS_CANCELLED,
            TeamInvite::STATUS_FAILED,
            TeamInvite::STATUS_EXPIRED,
        ], true))) {
            throw new \RuntimeException('inactive');
        }

        $acceptedCount = $groupInvites->where('status', TeamInvite::STATUS_ACCEPTED)->count();

        if ($acceptedCount < $required) {
            return [
                'ok' => true,
                'message' => "درخواست شما ثبت شد. منتظر تأیید سایر هم‌تیمی‌ها ({$acceptedCount}/{$required}).",
            ];
        }

        return $this->confirmSquadGroup($groupInvites, $tournament);
    }

    /** @param Collection<int, TeamInvite> $groupInvites */
    protected function confirmSquadGroup(Collection $groupInvites, Tournament $tournament): array
    {
        $lockedTournament = Tournament::where('id', $tournament->id)->lockForUpdate()->first();
        if (! $lockedTournament || ! $lockedTournament->acceptsRegistration()) {
            throw new \RuntimeException('closed');
        }

        $teamSeats = $this->findAvailableTeamSeats($lockedTournament);
        $mode = $lockedTournament->seatMode();
        if ($teamSeats === null || count($teamSeats) < $mode) {
            throw new \RuntimeException('no_team_slot');
        }

        $inviter = User::where('id', $groupInvites->first()->inviter_id)->lockForUpdate()->first();
        if (! $inviter) {
            throw new \RuntimeException('inviter_reg');
        }

        $inviterReg = Registration::where('user_id', $inviter->id)
            ->where('tournament_id', $lockedTournament->id)
            ->lockForUpdate()
            ->first();

        if (! $inviterReg || $inviterReg->seat_number !== null) {
            throw new \RuntimeException('inviter_reg');
        }

        $members = collect([$inviter]);
        foreach ($groupInvites as $groupInvite) {
            $invitee = User::where('id', $groupInvite->invitee_id)->lockForUpdate()->first();
            if (! $invitee) {
                throw new \RuntimeException('invitee_reg');
            }

            $exists = Registration::where('user_id', $invitee->id)
                ->where('tournament_id', $lockedTournament->id)
                ->lockForUpdate()
                ->exists();

            if ($exists) {
                throw new \RuntimeException('invitee_reg');
            }

            if ($invitee->wallet < (float) $lockedTournament->entry_fee) {
                throw new \RuntimeException($invitee->id === $inviter->id ? 'inviter_wallet' : 'invitee_wallet');
            }

            $members->push($invitee);
        }

        if ($inviter->wallet < (float) $lockedTournament->entry_fee) {
            throw new \RuntimeException('inviter_wallet');
        }

        foreach ($teamSeats as $seat) {
            $taken = Registration::where('tournament_id', $lockedTournament->id)
                ->where('seat_number', $seat)
                ->lockForUpdate()
                ->exists();
            if ($taken) {
                throw new \RuntimeException('seat_taken');
            }
        }

        foreach ($members as $member) {
            $this->chargeEntryFee($member, $lockedTournament);
        }

        $inviterReg->update([
            'seat_number' => $teamSeats[0],
            'status' => 'confirmed',
            'reservation_type' => 'team',
        ]);

        foreach ($groupInvites->values() as $index => $groupInvite) {
            Registration::create([
                'user_id' => $groupInvite->invitee_id,
                'tournament_id' => $lockedTournament->id,
                'seat_number' => $teamSeats[$index + 1],
                'status' => 'confirmed',
                'reservation_type' => 'team',
            ]);

            $groupInvite->update([
                'seat_number_inviter' => $teamSeats[0],
                'seat_number_invitee' => $teamSeats[$index + 1],
            ]);
        }

        $this->registrations->syncRegisteredCount($lockedTournament);

        foreach ($members as $member) {
            $this->activity->logTournament($member, 'tournament_joined', "ثبت‌نام تیمی در مسابقه: {$lockedTournament->title}", [
                'tournament_id' => $lockedTournament->id,
                'team_group_id' => $groupInvites->first()->team_group_id,
            ]);
        }

        TournamentListingService::forgetHomeCache();

        SendUserNotificationJob::dispatch(
            (int) $groupInvites->first()->inviter_id,
            'تأیید تیم کامل',
            sprintf('همه هم‌تیمی‌های شما در مسابقه «%s» دعوت را پذیرفتند.', $lockedTournament->title),
            'team_invite',
        );

        $labels = collect($teamSeats)->map(fn ($s) => $lockedTournament->seatDisplayLabel($s))->join('، ');

        return [
            'ok' => true,
            'message' => "رزرو تیمی ۴ نفره با موفقیت انجام شد. جایگاه‌ها: {$labels}",
        ];
    }

    protected function logTeamJoined(User $inviter, User $invitee, Tournament $tournament, int $seatInviter, int $seatInvitee): void
    {
        foreach ([$inviter, $invitee] as $member) {
            $this->activity->logTournament($member, 'tournament_joined', "ثبت‌نام تیمی در مسابقه: {$tournament->title}", [
                'tournament_id' => $tournament->id,
                'seat_number' => $member->id === $inviter->id ? $seatInviter : $seatInvitee,
            ]);
        }
    }

    /** @return array{ok:bool, message:string} */
    protected function failInvite(TeamInvite $invite, string $reason): array
    {
        $messages = [
            'inactive' => 'این درخواست دیگر فعال نیست.',
            'closed' => 'ثبت‌نام این مسابقه بسته شده است.',
            'no_team_slot' => 'جایگاه تیمی خالی برای این مسابقه وجود ندارد.',
            'inviter_wallet' => 'موجودی کیف پول درخواست‌دهنده کافی نیست. مبلغی کسر نشد.',
            'invitee_wallet' => 'موجودی کیف پول شما کافی نیست. مبلغی کسر نشد.',
            'inviter_reg' => 'ثبت‌نام درخواست‌دهنده نامعتبر است.',
            'invitee_reg' => 'شما قبلاً در این مسابقه ثبت‌نام کرده‌اید.',
            'seat_taken' => 'جایگاه انتخاب‌شده دیگر خالی نیست.',
            'internal' => 'خطای داخلی در رزرو تیمی. لطفاً دوباره تلاش کنید.',
        ];

        $message = $messages[$reason] ?? 'رزرو تیمی انجام نشد.';

        DB::transaction(function () use ($invite, $message) {
            $lockedInvite = TeamInvite::where('id', $invite->id)->lockForUpdate()->first();
            if (! $lockedInvite || ! $lockedInvite->isPending()) {
                return;
            }

            $lockedInvite->update([
                'status' => TeamInvite::STATUS_FAILED,
                'failure_reason' => $message,
            ]);

            Registration::where('user_id', $lockedInvite->inviter_id)
                ->where('tournament_id', $lockedInvite->tournament_id)
                ->whereNull('seat_number')
                ->delete();
        });

        return ['ok' => false, 'message' => $message];
    }

    /** @return array{ok:bool, message:string} */
    protected function failSquadGroup(TeamInvite $invite, string $reason): array
    {
        $messages = [
            'inactive' => 'این درخواست دیگر فعال نیست.',
            'closed' => 'ثبت‌نام این مسابقه بسته شده است.',
            'no_team_slot' => 'جایگاه تیمی خالی برای این مسابقه وجود ندارد.',
            'inviter_wallet' => 'موجودی کیف پول درخواست‌دهنده کافی نیست. مبلغی کسر نشد.',
            'invitee_wallet' => 'موجودی کیف پول یکی از اعضا کافی نیست. مبلغی کسر نشد.',
            'inviter_reg' => 'ثبت‌نام درخواست‌دهنده نامعتبر است.',
            'invitee_reg' => 'یکی از اعضا قبلاً در این مسابقه ثبت‌نام کرده است.',
            'seat_taken' => 'جایگاه انتخاب‌شده دیگر خالی نیست.',
            'internal' => 'خطای داخلی در رزرو تیمی. لطفاً دوباره تلاش کنید.',
        ];

        $message = $messages[$reason] ?? 'رزرو تیمی انجام نشد.';

        if ($invite->team_group_id) {
            DB::transaction(function () use ($invite, $message) {
                TeamInvite::query()
                    ->where('team_group_id', $invite->team_group_id)
                    ->whereNull('seat_number_invitee')
                    ->update([
                        'status' => TeamInvite::STATUS_FAILED,
                        'failure_reason' => $message,
                    ]);

                Registration::where('user_id', $invite->inviter_id)
                    ->where('tournament_id', $invite->tournament_id)
                    ->whereNull('seat_number')
                    ->delete();
            });
        } else {
            return $this->failInvite($invite, $reason);
        }

        return ['ok' => false, 'message' => $message];
    }

    public function cancelGroup(string $teamGroupId, int $inviterId, int $tournamentId): void
    {
        DB::transaction(function () use ($teamGroupId, $inviterId, $tournamentId) {
            TeamInvite::query()
                ->where('team_group_id', $teamGroupId)
                ->where('status', TeamInvite::STATUS_PENDING)
                ->update(['status' => TeamInvite::STATUS_CANCELLED]);

            Registration::where('user_id', $inviterId)
                ->where('tournament_id', $tournamentId)
                ->whereNull('seat_number')
                ->delete();
        });
    }

    protected function chargeEntryFee(User $user, Tournament $tournament): void
    {
        $fees = app(TournamentEntryFeeService::class);

        if ($fees->hasPaid($user, $tournament)) {
            return;
        }

        $fees->charge($user, $tournament);
    }
}
