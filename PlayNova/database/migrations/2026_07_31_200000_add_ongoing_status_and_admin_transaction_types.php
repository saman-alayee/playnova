<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN status ENUM('upcoming', 'active', 'ongoing', 'ended', 'cancelled') NOT NULL DEFAULT 'upcoming'");

        DB::statement("ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit', 'withdraw', 'fee', 'entry_fee', 'prize', 'refund', 'referral_bonus', 'admin_credit', 'admin_debit') NOT NULL");

        $statusColumn = DB::select("SHOW COLUMNS FROM transactions WHERE Field = 'status'");
        $statusType = $statusColumn[0]->Type ?? '';
        if (! str_contains($statusType, 'rejected')) {
            DB::statement("ALTER TABLE transactions MODIFY COLUMN status ENUM('pending', 'completed', 'failed', 'cancelled', 'rejected') NOT NULL DEFAULT 'pending'");
        }
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE tournaments MODIFY COLUMN status ENUM('upcoming', 'active', 'ended', 'cancelled') NOT NULL DEFAULT 'upcoming'");
    }
};
