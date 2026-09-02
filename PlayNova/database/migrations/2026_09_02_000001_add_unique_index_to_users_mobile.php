<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users') || ! Schema::hasColumn('users', 'mobile')) {
            return;
        }

        DB::table('users')->where('mobile', '')->update(['mobile' => null]);

        if ($this->indexExists('users', 'users_mobile_unique')) {
            return;
        }

        $hasDuplicates = DB::table('users')
            ->select('mobile')
            ->whereNotNull('mobile')
            ->groupBy('mobile')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            Log::warning('Skipped users.mobile unique index because duplicate mobiles already exist.');

            return;
        }

        if ($this->indexExists('users', 'users_mobile_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_mobile_index');
            });
        }

        try {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('mobile', 'users_mobile_unique');
            });
        } catch (\Throwable $e) {
            Log::warning('Could not add users.mobile unique index: '.$e->getMessage());

            if (! $this->indexExists('users', 'users_mobile_index') && ! $this->indexExists('users', 'users_mobile_unique')) {
                Schema::table('users', function (Blueprint $table) {
                    $table->index('mobile', 'users_mobile_index');
                });
            }
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if ($this->indexExists('users', 'users_mobile_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_mobile_unique');
            });
        }

        if (! $this->indexExists('users', 'users_mobile_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('mobile', 'users_mobile_index');
            });
        }
    }

    private function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $result !== [];
    }
};
