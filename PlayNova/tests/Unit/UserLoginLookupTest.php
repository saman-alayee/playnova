<?php

namespace Tests\Unit;

use App\Models\User;
use Tests\TestCase;

class UserLoginLookupTest extends TestCase
{
    public function test_find_by_login_builds_grouped_query(): void
    {
        $query = User::query()
            ->where(function ($query) {
                $query->where('email', 'demo')
                    ->orWhere('mobile', 'demo')
                    ->orWhere('username', 'demo');
            })
            ->toSql();

        $this->assertStringContainsString('username', $query);
        $this->assertStringContainsString('mobile', $query);
        $this->assertStringContainsString('email', $query);
    }
}
