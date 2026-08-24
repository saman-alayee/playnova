<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;

trait InvalidatesAdminDashboard
{
    protected function invalidateAdminDashboard(): void
    {
        Cache::forget('admin:dashboard:stats');
    }
}
