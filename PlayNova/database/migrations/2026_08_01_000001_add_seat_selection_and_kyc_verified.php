<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('tournaments', 'seat_mode')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->unsignedTinyInteger('seat_mode')->default(1)->after('capacity');
            });
        }

        if (! Schema::hasColumn('registrations', 'seat_number')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->unsignedInteger('seat_number')->nullable()->after('status');
                $table->unique(['tournament_id', 'seat_number'], 'registrations_tournament_seat_unique');
            });
        }

        if (! Schema::hasColumn('users', 'kyc_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->timestamp('kyc_verified_at')->nullable()->after('first_deposit_done');
            });
        }

        if (! Schema::hasColumn('kyc_submissions', 'document_path')) {
            Schema::table('kyc_submissions', function (Blueprint $table) {
                $table->string('document_path')->nullable()->after('card_back_path');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('kyc_submissions', 'document_path')) {
            Schema::table('kyc_submissions', function (Blueprint $table) {
                $table->dropColumn('document_path');
            });
        }
        if (Schema::hasColumn('users', 'kyc_verified_at')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('kyc_verified_at');
            });
        }
        if (Schema::hasColumn('registrations', 'seat_number')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropUnique('registrations_tournament_seat_unique');
                $table->dropColumn('seat_number');
            });
        }
        if (Schema::hasColumn('tournaments', 'seat_mode')) {
            Schema::table('tournaments', function (Blueprint $table) {
                $table->dropColumn('seat_mode');
            });
        }
    }
};