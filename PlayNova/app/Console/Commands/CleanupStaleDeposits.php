<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use Illuminate\Console\Command;

class CleanupStaleDeposits extends Command
{
    protected $signature = 'wallet:cleanup-stale-deposits';

    protected $description = 'Remove old pending/failed/cancelled deposit transactions';

    public function handle(): int
    {
        $deleted = Transaction::query()
            ->where('type', 'deposit')
            ->whereIn('status', ['pending', 'failed', 'cancelled'])
            ->where('created_at', '<', now()->subDay())
            ->delete();

        $this->info("Removed {$deleted} stale deposit record(s).");

        return self::SUCCESS;
    }
}
