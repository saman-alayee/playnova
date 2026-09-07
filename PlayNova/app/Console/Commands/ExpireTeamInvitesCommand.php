<?php

namespace App\Console\Commands;

use App\Models\TeamInvite;
use App\Services\TeamInviteService;
use Illuminate\Console\Command;

class ExpireTeamInvitesCommand extends Command
{
    protected $signature = 'team-invites:expire';

    protected $description = 'Expire pending team invites past their deadline';

    public function handle(TeamInviteService $teamInvites): int
    {
        $invites = TeamInvite::query()
            ->where('status', TeamInvite::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($invites as $invite) {
            $teamInvites->expireIfOverdue($invite);
        }

        $this->info('Expired ' . $invites->count() . ' invites.');

        return self::SUCCESS;
    }
}
