<?php

/**
 * One-time setup: team invites + seat admin role.
 * Run: php setup-team-features-once.php
 */

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;

echo "Setting up team reservation features...\n";

if (! Schema::hasTable('team_invites')) {
    Schema::create('team_invites', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
        $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
        $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
        $table->string('status', 20)->default('pending');
        $table->string('failure_reason', 500)->nullable();
        $table->unsignedInteger('seat_number_inviter')->nullable();
        $table->unsignedInteger('seat_number_invitee')->nullable();
        $table->timestamps();
    });
    echo "Created team_invites table.\n";
} else {
    echo "team_invites already exists.\n";
}

if (! Schema::hasColumn('users', 'is_seat_admin')) {
    Schema::table('users', function (Blueprint $table) {
        $table->boolean('is_seat_admin')->default(false)->after('is_admin');
    });
    echo "Added users.is_seat_admin.\n";
} else {
    echo "users.is_seat_admin already exists.\n";
}

if (! Schema::hasColumn('registrations', 'reservation_type')) {
    Schema::table('registrations', function (Blueprint $table) {
        $table->string('reservation_type', 10)->default('solo')->after('status');
    });
    echo "Added registrations.reservation_type.\n";
} else {
    echo "registrations.reservation_type already exists.\n";
}

echo "Done.\n";
