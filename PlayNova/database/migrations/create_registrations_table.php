<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::create('registrations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('tournament_id');
            $table->enum('status', ['registered', 'confirmed', 'cancelled', 'waiting'])->default('registered');
            $table->integer('rank')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('registrations');
    }
};