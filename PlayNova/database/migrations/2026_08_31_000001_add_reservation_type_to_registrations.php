<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('registrations') && ! Schema::hasColumn('registrations', 'reservation_type')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->string('reservation_type', 10)->default('solo')->after('status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('registrations', 'reservation_type')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropColumn('reservation_type');
            });
        }
    }
};
