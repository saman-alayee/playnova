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

            $allForLeagues = Tournament::whereIn('status', ['active', 'upcoming', 'ongoing'])
                ->withCount(['registrations as registrations_count' => $confirmedRegistrations])
                ->orderBy('start_date')
                ->get();

            $activeTournaments = $allForLeagues
                ->filter(fn ($tournament) => in_array($tournament->status, ['active', 'ongoing'], true))
                ->when($hasLeague, fn ($collection) => $collection->where('league', 'professional'))
                ->sortByDesc('prize_pool')
                ->sortBy('start_date')
                ->values();

            $leagues = [
                'beginner' => collect(),
                'intermediate' => collect(),
                'professional' => collect(),
            ];

            if ($hasLeague) {
                foreach ($allForLeagues as $tournament) {
                    $key = in_array($tournament->league, array_keys($leagues), true)
                        ? $tournament->league
                        : 'intermediate';
                    $leagues[$key]->push($tournament);
                }
            } else {
                $leagues['intermediate'] = $allForLeagues;
            }

            $news = News::where('is_published', true)
                ->orderByDesc('created_at')
                ->take(5)
                ->get();

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
