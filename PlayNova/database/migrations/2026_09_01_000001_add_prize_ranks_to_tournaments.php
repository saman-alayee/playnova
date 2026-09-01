<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournaments', 'prize_ranks')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->json('prize_ranks')->nullable()->after('prize_pool');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('tournaments', 'prize_ranks')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('prize_ranks');
            });
        }
    }
};
