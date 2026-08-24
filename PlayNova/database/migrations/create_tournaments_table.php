<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('tournaments', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('game')->default('Call of Duty Mobile');
            $table->text('description')->nullable();
            $table->decimal('entry_fee', 15, 0);
            $table->decimal('prize_pool', 15, 0);
            $table->integer('capacity');
            $table->integer('registered_count')->default(0);
            $table->dateTime('start_date');
            $table->dateTime('end_date')->nullable();
            $table->enum('status', ['upcoming', 'active', 'ended', 'cancelled'])->default('upcoming');
            $table->unsignedBigInteger('winner_id')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('tournaments');
    }
};