<?php

namespace App\Console\Commands;

use App\Jobs\ExpireTeamInviteJob;
use App\Models\TeamInvite;
use Illuminate\Console\Command;

class ExpireTeamInvitesCommand extends Command
{
    protected $signature = 'team-invites:expire';

    protected $description = 'Expire pending team invites past their deadline';

    public function handle(): int
    {
        $ids = TeamInvite::query()
            ->where('status', TeamInvite::STATUS_PENDING)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', now())
            ->pluck('id');

        foreach ($ids as $id) {
            ExpireTeamInviteJob::dispatch($id);
        }

        $this->info('Queued expiry for ' . $ids->count() . ' invites.');

        return self::SUCCESS;
    }
}
