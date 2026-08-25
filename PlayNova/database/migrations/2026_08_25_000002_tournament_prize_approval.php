<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('tournament_prize_batches')) {
            Schema::create('tournament_prize_batches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('tournament_id')->constrained()->cascadeOnDelete();
                $table->string('status', 32)->default('pending_approval');
                $table->decimal('total_amount', 15, 2)->default(0);
                $table->foreignId('winner_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('approved_at')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique('tournament_id');
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('tournament_prize_entries')) {
            Schema::create('tournament_prize_entries', function (Blueprint $table) {
                $table->id();
                $table->foreignId('batch_id')->constrained('tournament_prize_batches')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedSmallInteger('rank')->nullable();
                $table->string('team_label', 32)->nullable();
                $table->unsignedInteger('seat_number')->nullable();
                $table->decimal('prize_amount', 15, 2)->default(0);
                $table->unsignedInteger('kills')->nullable();
                $table->json('metadata')->nullable();
                $table->timestamps();

                $table->unique(['batch_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tournament_prize_entries');
        Schema::dropIfExists('tournament_prize_batches');
    }
};