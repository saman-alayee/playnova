<?php

namespace App\Http\Controllers\Api\V1\Admin\Concerns;

trait AuthorizesAdmin
{
    protected function authorizeAdmin(): void
    {
        $user = request()->user();
        if (! $user || ! $user->isAdmin()) {
            abort(403, 'دسترسی ادمین لازم است.');
        }
    }

    protected function authorizeSeatAdmin(): void
    {
        $user = request()->user();
        if (! $user || (! $user->isAdmin() && ! $user->is_seat_admin)) {
            abort(403, 'دسترسی لازم است.');
        }
    }
}
