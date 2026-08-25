<?php

namespace App\Modules\Tournament\Services;

use App\Models\Registration;
use App\Models\TeamInvite;
use App\Models\Tournament;
use Illuminate\Support\Facades\DB;

class TournamentRegistrationGuard
{
    /** Cancel incomplete registrations when tournament is no longer open. */
    public function closeOpenRegistrations(Tournament $tournament): void
    {
        if ($tournament->acceptsRegistration()) {
            return;
        }

        DB::transaction(function () use ($tournament) {
            TeamInvite::query()
                ->where('tournament_id', $tournament->id)
                ->where('status', TeamInvite::STATUS_PENDING)
                ->update(['status' => TeamInvite::STATUS_CANCELLED]);

            Registration::query()
                ->where('tournament_id', $tournament->id)
                ->whereNull('seat_number')
                ->delete();
        });
    }

    public function assertCanRegister(Tournament $tournament): void
    {
        $fresh = Tournament::query()->whereKey($tournament->id)->first();

        if (! $fresh || ! $fresh->acceptsRegistration()) {
            throw new \RuntimeException('registration_closed');
        }
    }
}
