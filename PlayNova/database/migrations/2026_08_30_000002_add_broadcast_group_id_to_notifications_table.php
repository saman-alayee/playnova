<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'broadcast_group_id')) {
                $table->uuid('broadcast_group_id')->nullable()->after('type');
                $table->index(['type', 'broadcast_group_id']);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('notifications')) {
            return;
        }

        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'broadcast_group_id')) {
                $table->dropIndex(['type', 'broadcast_group_id']);
                $table->dropColumn('broadcast_group_id');
            }
        });
    }
};
