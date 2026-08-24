<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('wins')->default(0)->after('password');
            $table->integer('losses')->default(0)->after('wins');
            $table->decimal('wallet', 15, 2)->default(0.00)->after('losses');
            $table->string('cod_id')->nullable()->after('wallet');
            $table->string('username')->nullable()->after('id');
            $table->string('mobile')->nullable()->after('email');
            $table->string('referral_code')->nullable()->unique()->after('cod_id');
            $table->unsignedBigInteger('referred_by')->nullable()->after('referral_code');
            $table->boolean('is_admin')->default(false)->after('referred_by');
            $table->boolean('first_deposit_done')->default(false)->after('is_admin');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'wins', 'losses', 'wallet', 'cod_id',
                'username', 'mobile', 'referral_code',
                'referred_by', 'is_admin', 'first_deposit_done'
            ]);
        });
    }
};