<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SchemaFeatures
{
    private const CACHE_TTL_SECONDS = 86400;

    public static function hasTeamInvitesTable(): bool
    {
        return Cache::remember('schema:has_team_invites', self::CACHE_TTL_SECONDS, fn () => Schema::hasTable('team_invites'));
    }

    public static function tournamentsHaveLeagueColumn(): bool
    {
        return Cache::remember('schema:tournaments_have_league', self::CACHE_TTL_SECONDS, fn () => Schema::hasColumn('tournaments', 'league'));
    }

    public static function forget(): void
    {
        Cache::forget('schema:has_team_invites');
        Cache::forget('schema:tournaments_have_league');
    }
}
