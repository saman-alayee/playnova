<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndexIfMissing('registrations', ['user_id', 'tournament_id'], 'registrations_user_tournament_index');
        $this->addIndexIfMissing('registrations', ['tournament_id', 'status'], 'registrations_tournament_status_index');

        $this->addIndexIfMissing('transactions', ['user_id', 'created_at'], 'transactions_user_created_index');
        $this->addIndexIfMissing('transactions', ['type', 'status'], 'transactions_type_status_index');
        $this->addIndexIfMissing('transactions', ['reference_id'], 'transactions_reference_id_index');

        $this->addIndexIfMissing('notifications', ['user_id', 'is_read', 'created_at'], 'notifications_user_read_created_index');
        $this->addIndexIfMissing('notifications', ['type', 'created_at'], 'notifications_type_created_index');

        $this->addIndexIfMissing('users', ['mobile'], 'users_mobile_index');
        $this->addIndexIfMissing('users', ['cod_id'], 'users_cod_id_index');
        $this->addIndexIfMissing('users', ['kills'], 'users_kills_index');

        $this->addIndexIfMissing('tournaments', ['status', 'start_date'], 'tournaments_status_start_index');

        if (Schema::hasColumn('tournaments', 'league')) {
            $this->addIndexIfMissing('tournaments', ['status', 'league', 'start_date'], 'tournaments_status_league_start_index');
        }

        $this->addIndexIfMissing('tickets', ['status', 'created_at'], 'tickets_status_created_index');
        $this->addIndexIfMissing('tickets', ['user_id', 'created_at'], 'tickets_user_created_index');

        if (Schema::hasTable('team_invites')) {
            $this->addIndexIfMissing('team_invites', ['invitee_id', 'status'], 'team_invites_invitee_status_index');
            $this->addIndexIfMissing('team_invites', ['inviter_id', 'status'], 'team_invites_inviter_status_index');
        }
    }

    public function down(): void
    {
        $this->dropIndexIfExists('registrations', 'registrations_user_tournament_index');
        $this->dropIndexIfExists('registrations', 'registrations_tournament_status_index');

        $this->dropIndexIfExists('transactions', 'transactions_user_created_index');
        $this->dropIndexIfExists('transactions', 'transactions_type_status_index');
        $this->dropIndexIfExists('transactions', 'transactions_reference_id_index');

        $this->dropIndexIfExists('notifications', 'notifications_user_read_created_index');
        $this->dropIndexIfExists('notifications', 'notifications_type_created_index');

        $this->dropIndexIfExists('users', 'users_mobile_index');
        $this->dropIndexIfExists('users', 'users_cod_id_index');
        $this->dropIndexIfExists('users', 'users_kills_index');

        $this->dropIndexIfExists('tournaments', 'tournaments_status_start_index');
        $this->dropIndexIfExists('tournaments', 'tournaments_status_league_start_index');

        $this->dropIndexIfExists('tickets', 'tickets_status_created_index');
        $this->dropIndexIfExists('tickets', 'tickets_user_created_index');

        $this->dropIndexIfExists('team_invites', 'team_invites_invitee_status_index');
        $this->dropIndexIfExists('team_invites', 'team_invites_inviter_status_index');
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
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        $result = $connection->select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [$database, $table, $indexName]
        );

        return $result !== [];
    }
};
