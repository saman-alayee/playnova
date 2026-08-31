<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'cod_id')) {
            return;
        }

        DB::table('users')
            ->whereNotNull('cod_id')
            ->update(['cod_id' => DB::raw('TRIM(cod_id)')]);

        $duplicateGroups = DB::table('users')
            ->selectRaw('LOWER(TRIM(cod_id)) AS normalized, MIN(id) AS keep_id')
            ->whereNotNull('cod_id')
            ->where('cod_id', '!=', '')
            ->groupByRaw('LOWER(TRIM(cod_id))')
            ->havingRaw('COUNT(*) > 1')
            ->get();

        foreach ($duplicateGroups as $group) {
            DB::table('users')
                ->whereRaw('LOWER(TRIM(cod_id)) = ?', [$group->normalized])
                ->where('id', '!=', $group->keep_id)
                ->update(['cod_id' => null]);
        }

        if ($this->indexExists('users', 'users_cod_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_cod_id_index');
            });
        }

        if (! $this->indexExists('users', 'users_cod_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('cod_id');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('users', 'cod_id')) {
            return;
        }

        if ($this->indexExists('users', 'users_cod_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique(['cod_id']);
            });
        }

        if (! $this->indexExists('users', 'users_cod_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('cod_id', 'users_cod_id_index');
            });
        }
    }

    protected function indexExists(string $table, string $indexName): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return ! empty($result);
    }
};
