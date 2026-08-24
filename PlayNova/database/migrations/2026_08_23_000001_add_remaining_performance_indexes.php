<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('users', ['referred_by'], 'users_referred_by_index');

        if (Schema::hasTable('registrations')) {
            $this->addIndexIfMissing('registrations', ['user_id', 'tournament_id'], 'registrations_user_tournament_lookup_index');
        }

        if (Schema::hasTable('discounts')) {
            $this->addIndexIfMissing('discounts', ['is_active'], 'discounts_is_active_index');
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('users', 'users_referred_by_index');
        $this->dropIndexIfExists('registrations', 'registrations_user_tournament_lookup_index');
        $this->dropIndexIfExists('discounts', 'discounts_is_active_index');
    }

    private function addIndexIfMissing(string $table, array|string $columns, string $indexName): void
    {
        if (! Schema::hasTable($table) || $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($columns, $indexName) {
            $blueprint->index($columns, $indexName);
        });
    }

    private function dropIndexIfExists(string $table, string $indexName): void
    {
        if (! Schema::hasTable($table) || ! $this->indexExists($table, $indexName)) {
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName) {
            $blueprint->dropIndex($indexName);
        });
    }

    private function indexExists(string $table, string $indexName): bool
    {
        return $this->statisticsExists($table, $indexName);
    }

    private function uniqueExists(string $table, string $indexName): bool
    {
        return $this->statisticsExists($table, $indexName);
    }

    private function statisticsExists(string $table, string $indexName): bool
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
