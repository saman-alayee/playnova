<?php



namespace App\Jobs;



use App\Models\Registration;

use App\Models\TeamInvite;

use App\Services\TeamInviteService;

use Illuminate\Bus\Queueable;

use Illuminate\Contracts\Queue\ShouldQueue;

use Illuminate\Foundation\Bus\Dispatchable;

use Illuminate\Queue\InteractsWithQueue;

use Illuminate\Queue\SerializesModels;

use Illuminate\Support\Facades\DB;



class ExpireTeamInviteJob implements ShouldQueue

{

    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;



    public function __construct(public int $inviteId)

    {

    }



    public function handle(TeamInviteService $teamInvites): void

    {

        $invite = TeamInvite::find($this->inviteId);

        if (! $invite) {

            return;

        }



        if ($invite->team_group_id) {

            $this->expireGroup($invite, $teamInvites);



            return;

        }



        $this->expireSingle($invite, $teamInvites);

    }



    protected function expireSingle(TeamInvite $invite, TeamInviteService $teamInvites): void

    {

        DB::transaction(function () use ($teamInvites) {

            $lockedInvite = TeamInvite::query()->whereKey($this->inviteId)->lockForUpdate()->first();



            if (! $lockedInvite || ! $lockedInvite->isPending()) {

                return;

            }



            if ($lockedInvite->expires_at && $lockedInvite->expires_at->isFuture()) {

                return;

            }



            $lockedInvite->update(['status' => TeamInvite::STATUS_EXPIRED]);



            Registration::query()

                ->where('user_id', $lockedInvite->inviter_id)

                ->where('tournament_id', $lockedInvite->tournament_id)

                ->whereNull('seat_number')

                ->delete();

        });



        $this->clearCaches($invite, $teamInvites);

    }



    protected function expireGroup(TeamInvite $invite, TeamInviteService $teamInvites): void

    {

        DB::transaction(function () use ($invite) {

            $groupInvites = TeamInvite::query()

                ->where('team_group_id', $invite->team_group_id)

                ->lockForUpdate()

                ->get();



            if ($groupInvites->isEmpty()) {

                return;

            }



            $first = $groupInvites->first();

            if ($first->expires_at && $first->expires_at->isFuture()) {

                return;

            }



            if ($groupInvites->contains(fn (TeamInvite $i) => $i->seat_number_invitee !== null)) {

                return;

            }



            foreach ($groupInvites as $groupInvite) {

                if ($groupInvite->isPending()) {

                    $groupInvite->update(['status' => TeamInvite::STATUS_EXPIRED]);

                }

            }



            Registration::query()

                ->where('user_id', $first->inviter_id)

                ->where('tournament_id', $first->tournament_id)

                ->whereNull('seat_number')

                ->delete();

        });



        $this->clearCaches($invite, $teamInvites);

    }



    protected function clearCaches(TeamInvite $invite, TeamInviteService $teamInvites): void

    {

        $teamInvites->forgetForUser((int) $invite->inviter_id);

        $teamInvites->forgetForUser((int) $invite->invitee_id);



        if ($invite->team_group_id) {

            TeamInvite::query()

                ->where('team_group_id', $invite->team_group_id)

                ->get(['inviter_id', 'invitee_id'])

                ->each(function (TeamInvite $related) use ($teamInvites) {

                    $teamInvites->forgetForUser((int) $related->inviter_id);

                    $teamInvites->forgetForUser((int) $related->invitee_id);

                });

        }

    }

}

