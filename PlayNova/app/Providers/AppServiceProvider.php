<?php

namespace App\Providers;

use App\Services\TeamInviteService;
use App\Support\ProductionConfig;
use App\Support\SchemaFeatures;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ProductionConfig::assertReady();

        view()->composer('layouts.app', function ($view) {
            if (! auth()->check() || ! SchemaFeatures::hasTeamInvitesTable()) {
                $view->with([
                    'pendingTeamInvites' => collect(),
                    'sentTeamInvites' => collect(),
                ]);

                return;
            }

            $userId = auth()->id();
            $teamInviteService = app(TeamInviteService::class);

            $view->with([
                'pendingTeamInvites' => $teamInviteService->pendingForUser($userId),
                'sentTeamInvites' => $teamInviteService->sentForUser($userId),
            ]);
        });
    }
}
