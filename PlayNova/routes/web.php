<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes — API backend only; UI is served by Nuxt (FRONTEND_URL).
|--------------------------------------------------------------------------
*/

Route::get('/wallet/callback', function () {
    $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');
    $query = request()->getQueryString();

    return redirect($frontend . '/wallet/callback' . ($query ? '?' . $query : ''));
})->name('wallet.callback');

Route::fallback(function () {
    if (request()->is('api/*') || request()->is('sanctum/*')) {
        abort(404);
    }

    $frontend = rtrim((string) config('app.frontend_url', env('FRONTEND_URL', 'http://127.0.0.1:3000')), '/');

    return redirect($frontend . request()->getRequestUri());
});
