<?php

namespace App\Jobs;

use App\Models\TeamInvite;
use App\Services\TeamInviteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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

        $teamInvites->expireIfOverdue($invite);
    }
}
