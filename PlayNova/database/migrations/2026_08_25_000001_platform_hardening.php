<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activity_logs')) {
            Schema::create('activity_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('category', 64);
                $table->string('action', 64);
                $table->text('description')->nullable();
                $table->json('metadata')->nullable();
                $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();

                $table->index(['user_id', 'created_at']);
                $table->index(['category', 'created_at']);
            });
        }

        if (Schema::hasTable('team_invites')) {
            Schema::table('team_invites', function (Blueprint $table) {
                if (! Schema::hasColumn('team_invites', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('status');
                }
                if (! Schema::hasColumn('team_invites', 'team_group_id')) {
                    $table->uuid('team_group_id')->nullable()->after('tournament_id');
                }
            });

            if (Schema::hasColumn('team_invites', 'expires_at')) {
                Schema::table('team_invites', function (Blueprint $table) {
                    $table->index(['status', 'expires_at']);
                });
            }
        }

        if (Schema::hasTable('registrations')) {
            $dupes = DB::table('registrations')
                ->select('user_id', 'tournament_id', DB::raw('COUNT(*) as c'))
                ->groupBy('user_id', 'tournament_id')
                ->having('c', '>', 1)
                ->get();

            foreach ($dupes as $row) {
                $ids = DB::table('registrations')
                    ->where('user_id', $row->user_id)
                    ->where('tournament_id', $row->tournament_id)
                    ->orderByRaw('CASE WHEN seat_number IS NOT NULL THEN 0 ELSE 1 END')
                    ->orderBy('id')
                    ->pluck('id');

                $keep = $ids->shift();
                if ($ids->isNotEmpty()) {
                    DB::table('registrations')->whereIn('id', $ids->all())->delete();
                }
            }

            $indexName = 'registrations_user_id_tournament_id_unique';
            $exists = collect(DB::select("SHOW INDEX FROM registrations WHERE Key_name = ?", [$indexName]))->isNotEmpty();

            if (! $exists) {
                Schema::table('registrations', function (Blueprint $table) {
                    $table->unique(['user_id', 'tournament_id']);
                });
            }
        }

        if (Schema::hasTable('transactions')) {
            $indexName = 'transactions_user_reference_type_unique';
            $exists = collect(DB::select("SHOW INDEX FROM transactions WHERE Key_name = ?", [$indexName]))->isNotEmpty();

            if (! $exists) {
                Schema::table('transactions', function (Blueprint $table) {
                    $table->unique(['user_id', 'reference_id', 'type'], 'transactions_user_reference_type_unique');
                });
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');

        if (Schema::hasTable('team_invites')) {
            Schema::table('team_invites', function (Blueprint $table) {
                if (Schema::hasColumn('team_invites', 'expires_at')) {
                    $table->dropColumn('expires_at');
                }
                if (Schema::hasColumn('team_invites', 'team_group_id')) {
                    $table->dropColumn('team_group_id');
                }
            });
        }

        if (Schema::hasTable('registrations')) {
            Schema::table('registrations', function (Blueprint $table) {
                $table->dropUnique(['user_id', 'tournament_id']);
            });
        }

        if (Schema::hasTable('transactions')) {
            Schema::table('transactions', function (Blueprint $table) {
                $table->dropUnique('transactions_user_reference_type_unique');
            });
        }
    }
};
