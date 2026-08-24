<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'bank_card_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('bank_card_number', 24)->nullable()->after('cod_id');
            });
        }

        if (! Schema::hasColumn('users', 'bank_account_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('bank_account_name', 120)->nullable()->after('bank_card_number');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'bank_account_name')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('bank_account_name');
            });
        }
        if (Schema::hasColumn('users', 'bank_card_number')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('bank_card_number');
            });
        }
    }
};