<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'username')) {
                $table->string('username')->nullable()->after('id');
            }
            if (!Schema::hasColumn('users', 'mobile')) {
                $table->string('mobile')->nullable()->after('email');
            }
            if (!Schema::hasColumn('users', 'referral_code')) {
                $table->string('referral_code')->nullable()->unique()->after('cod_id');
            }
            if (!Schema::hasColumn('users', 'referred_by')) {
                $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            }
            if (!Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->after('referred_by');
            }
            if (!Schema::hasColumn('users', 'first_deposit_done')) {
                $table->boolean('first_deposit_done')->default(false)->after('is_admin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'username', 'mobile', 'referral_code',
                'referred_by', 'is_admin', 'first_deposit_done'
            ]);
        });
    }
};