<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('api_error_logs')) {
            return;
        }

        Schema::create('api_error_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('status_code')->default(500);
            $table->string('method', 10);
            $table->string('endpoint', 512);
            $table->text('message');
            $table->string('exception_class')->nullable();
            $table->longText('stack_trace')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->json('context')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['resolved_at', 'created_at']);
            $table->index(['status_code', 'created_at']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_error_logs');
    }
};
