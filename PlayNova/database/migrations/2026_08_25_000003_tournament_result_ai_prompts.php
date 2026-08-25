<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (! Schema::hasColumn('tournaments', 'result_ai_system_prompt')) {
                $table->text('result_ai_system_prompt')->nullable()->after('game_login_info');
            }
            if (! Schema::hasColumn('tournaments', 'result_ai_user_prompt')) {
                $table->text('result_ai_user_prompt')->nullable()->after('result_ai_system_prompt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('tournaments', function (Blueprint $table) {
            if (Schema::hasColumn('tournaments', 'result_ai_user_prompt')) {
                $table->dropColumn('result_ai_user_prompt');
            }
            if (Schema::hasColumn('tournaments', 'result_ai_system_prompt')) {
                $table->dropColumn('result_ai_system_prompt');
            }
        });
    }
};
