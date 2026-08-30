<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('news')) {
            $this->addIndexIfMissing('news', ['is_published', 'created_at'], 'news_published_created_index');
        }

        if (Schema::hasTable('tournaments')) {
            $this->addIndexIfMissing('tournaments', ['status', 'end_date'], 'tournaments_status_end_index');
        }

        if (Schema::hasTable('kyc_submissions')) {
            $this->addIndexIfMissing('kyc_submissions', ['status', 'created_at'], 'kyc_submissions_status_created_index');
            $this->addIndexIfMissing('kyc_submissions', ['user_id', 'status'], 'kyc_submissions_user_status_index');
        }

        if (Schema::hasTable('team_invites')) {
            $this->addIndexIfMissing('team_invites', ['tournament_id', 'status'], 'team_invites_tournament_status_index');
        }

        $this->addIndexIfMissing('users', ['username'], 'users_username_index');
        $this->addIndexIfMissing('users', ['email'], 'users_email_index');

        $this->dropIndexIfExists('registrations', 'registrations_user_tournament_lookup_index');
        $this->dropIndexIfExists('registrations', 'registrations_user_tournament_index');
    }

    public function down(): void
    {
        $this->dropIndexIfExists('news', 'news_published_created_index');
        $this->dropIndexIfExists('tournaments', 'tournaments_status_end_index');
        $this->dropIndexIfExists('kyc_submissions', 'kyc_submissions_status_created_index');
        $this->dropIndexIfExists('kyc_submissions', 'kyc_submissions_user_status_index');
        $this->dropIndexIfExists('team_invites', 'team_invites_tournament_status_index');
        $this->dropIndexIfExists('users', 'users_username_index');
        $this->dropIndexIfExists('users', 'users_email_index');

        $this->addIndexIfMissing('registrations', ['user_id', 'tournament_id'], 'registrations_user_tournament_index');
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
