<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiRateLimiterTest extends TestCase
{
    public function test_browse_invite_and_register_limiters_are_split(): void
    {
        $request = Request::create('/api/v1/home', 'GET');

        $api = RateLimiter::limiter('api')($request);
        $invites = RateLimiter::limiter('invites')($request);
        $register = RateLimiter::limiter('register')($request);
        $auth = RateLimiter::limiter('auth')($request);

        $this->assertSame(120, $api->maxAttempts);
        $this->assertSame(30, $invites->maxAttempts);
        $this->assertSame(10, $register->maxAttempts);
        $this->assertSame(15, $auth->maxAttempts);
    }
}
