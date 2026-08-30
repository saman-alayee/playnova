<?php

namespace App\Modules\Tournament\Services;

use App\Models\News;
use App\Models\Tournament;
use App\Models\User;
use App\Support\SchemaFeatures;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TournamentListingService
{
    private const HOME_CACHE_SECONDS = 120;

    private const LEADERBOARD_CACHE_SECONDS = 300;

    public function homePayload(): array
    {
        return Cache::remember('public:home:v1', self::HOME_CACHE_SECONDS, function () {
            $hasLeague = SchemaFeatures::tournamentsHaveLeagueColumn();
            $confirmedRegistrations = fn ($query) => $query->whereNotNull('seat_number');

            $baseQuery = fn () => Tournament::query()
                ->whereIn('status', ['active', 'upcoming', 'ongoing'])
                ->withCount(['registrations as registrations_count' => $confirmedRegistrations]);

            $news = News::where('is_published', true)
                ->orderByDesc('created_at')
                ->take(5)
                ->get();

            if ($hasLeague) {
                $activeTournaments = $baseQuery()
                    ->whereIn('status', ['active', 'ongoing'])
                    ->where('league', 'professional')
                    ->orderByDesc('prize_pool')
                    ->orderBy('start_date')
                    ->get()
                    ->values();

                $leagues = [
                    'beginner' => $baseQuery()
                        ->where('league', 'beginner')
                        ->orderBy('start_date')
                        ->get(),
                    'intermediate' => $baseQuery()
                        ->where('league', 'intermediate')
                        ->orderBy('start_date')
                        ->get(),
                    'professional' => $baseQuery()
                        ->where('league', 'professional')
                        ->orderBy('start_date')
                        ->get(),
                ];
            } else {
                $all = $baseQuery()->orderBy('start_date')->get();

                $activeTournaments = $all
                    ->filter(fn ($tournament) => in_array($tournament->status, ['active', 'ongoing'], true))
                    ->sortByDesc('prize_pool')
                    ->sortBy('start_date')
                    ->values();

                $leagues = [
                    'beginner' => collect(),
                    'intermediate' => $all,
                    'professional' => collect(),
                ];
            }

            return compact('activeTournaments', 'leagues', 'news');
        });
    }

    public function leaderboard(): Collection
    {
        return Cache::remember('public:leaderboard:v1', self::LEADERBOARD_CACHE_SECONDS, fn () => User::query()
            ->where('kills', '>', 0)
            ->orderByDesc('kills')
            ->orderBy('username')
            ->take(100)
            ->get(['id', 'username', 'kills', 'wins', 'losses']));
    }

    public static function forgetHomeCache(): void
    {
        Cache::forget('public:home:v1');
    }

    public static function forgetLeaderboardCache(): void
    {
        Cache::forget('public:leaderboard:v1');
    }
}
