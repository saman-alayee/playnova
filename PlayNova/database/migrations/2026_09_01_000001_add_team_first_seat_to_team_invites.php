<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('team_invites')) {
            return;
        }

        Schema::table('team_invites', function (Blueprint $table) {
            if (! Schema::hasColumn('team_invites', 'team_first_seat')) {
                $table->unsignedInteger('team_first_seat')->nullable()->after('team_group_id');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('team_invites')) {
            return;
        }

        Schema::table('team_invites', function (Blueprint $table) {
            if (Schema::hasColumn('team_invites', 'team_first_seat')) {
                $table->dropColumn('team_first_seat');
            }
        });
    }
};
