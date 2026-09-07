<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('team_invites')) {
            return;
        }

        Schema::create('team_invites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
            $table->foreignId('inviter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('invitee_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('failure_reason', 500)->nullable();
            $table->unsignedInteger('seat_number_inviter')->nullable();
            $table->unsignedInteger('seat_number_invitee')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->string('team_group_id', 36)->nullable();
            $table->unsignedInteger('team_first_seat')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_invites');
    }
};
