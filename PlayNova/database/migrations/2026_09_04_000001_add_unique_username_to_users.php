<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('username')
            ->update(['username' => DB::raw('TRIM(username)')]);

        $duplicateGroups = DB::table('users')
            ->selectRaw('LOWER(TRIM(username)) AS normalized, MIN(id) AS keep_id')
            ->whereNotNull('username')
            ->where('username', '!=', '')
            ->groupByRaw('LOWER(TRIM(username))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            $duplicates = DB::table('users')
                ->whereRaw('LOWER(TRIM(username)) = ?', [$group->normalized])
                ->where('id', '!=', $group->keep_id)
                ->get(['id', 'username']);

            foreach ($duplicates as $user) {
                DB::table('users')
                    ->where('id', $user->id)
                    ->update(['username' => 'dup_' . $user->id . '_' . $user->username]);
            }
        }

        if ($this->indexExists('users', 'users_username_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_username_index');
            });
        }

        if (! $this->indexExists('users', 'users_username_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('username');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'username')) {
            return;
        }

        if ($this->indexExists('users', 'users_username_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['username']);
            });
        }

        if (! $this->indexExists('users', 'users_username_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('username', 'users_username_index');
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();

        if ($connection->getDriverName() === 'sqlite') {
            $indexes = DB::select("PRAGMA index_list('{$table}')");

            foreach ($indexes as $index) {
                if (($index->name ?? null) === $indexName) {
                    return true;
                }
            }

            return false;
        }

        $database = $connection->getDatabaseName();

        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return ! empty($result);
    }
};
