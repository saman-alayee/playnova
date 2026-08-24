<?php

namespace App\Services;

use App\Jobs\SendUserNotificationJob;
use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use App\Models\Transaction;
use App\Models\User;
use App\Modules\Tournament\Services\TournamentListingService;
use Illuminate\Support\Facades\DB;

class TeamReservationService
{
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

    public function notify(User $user, string $title, string $message, string $type = 'team_invite'): void
    {
        SendUserNotificationJob::dispatch($user->id, $title, $message, $type);
    }

    /** @return array{ok:bool, message:string} */
    public function accept(TeamInvite $invite): array
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

                $this->chargeEntryFee($inviter, $tournament, $fee);
                $this->chargeEntryFee($invitee, $tournament, $fee);

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

                $tournament->increment('registered_count', 2);

                $lockedInvite->update([
                    'status' => TeamInvite::STATUS_ACCEPTED,
                    'seat_number_inviter' => $seatInviter,
                    'seat_number_invitee' => $seatInvitee,
                    'failure_reason' => null,
                ]);

                $labelInviter = $tournament->seatDisplayLabel($seatInviter);
                $labelInvitee = $tournament->seatDisplayLabel($seatInvitee);

                $this->notify(
                    $inviter,
                    'رزرو تیمی تأیید شد',
                    "هم‌تیمی شما درخواست را پذیرفت. جایگاه‌های {$labelInviter} و {$labelInvitee} برای شما رزرو شد.",
                    'team_invite_accepted'
                );

                $this->notify(
                    $invitee,
                    'رزرو تیمی تکمیل شد',
                    "شما درخواست {$inviter->username} را پذیرفتید. جایگاه {$labelInvitee} برای شما رزرو شد.",
                    'team_invite_accepted'
                );

                TournamentListingService::forgetHomeCache();

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

        DB::transaction(function () use ($invite, $message, $reason) {
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

            $inviter = User::find($lockedInvite->inviter_id);
            $invitee = User::find($lockedInvite->invitee_id);
            $tournament = Tournament::find($lockedInvite->tournament_id);
            $title = $tournament?->title ?? 'مسابقه';

            if ($inviter) {
                $this->notify(
                    $inviter,
                    'رزرو تیمی ناموفق',
                    "{$title}: {$message}",
                    'team_invite_failed'
                );
            }

            if ($invitee && in_array($reason, ['invitee_wallet', 'no_team_slot', 'seat_taken'], true)) {
                $this->notify(
                    $invitee,
                    'رزرو تیمی ناموفق',
                    "{$title}: {$message}",
                    'team_invite_failed'
                );
            }
        });

        return ['ok' => false, 'message' => $message];
    }

    protected function chargeEntryFee(User $user, Tournament $tournament, float $fee): void
    {
        $alreadyPaid = Transaction::where('user_id', $user->id)
            ->where('type', 'fee')
            ->where('status', 'completed')
            ->where('description', 'like', '%' . $tournament->title . '%')
            ->exists();

        if ($alreadyPaid) {
            return;
        }

        $user->wallet = round($user->wallet - $fee, 2);
        $user->save();

        Transaction::create([
            'user_id' => $user->id,
            'type' => 'fee',
            'amount' => $fee,
            'balance_after' => $user->wallet,
            'description' => "هزینه ثبت‌نام تیمی در مسابقه: {$tournament->title}",
            'status' => 'completed',
        ]);
    }
}
