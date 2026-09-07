<?php

namespace Tests\Unit;

use App\Models\TeamInvite;
use Tests\TestCase;

class TeamInvitePendingStateTest extends TestCase
{
    public function test_open_invite_past_deadline_is_not_pending(): void
    {
        $invite = new TeamInvite([
            'status' => TeamInvite::STATUS_PENDING,
            'expires_at' => now()->subSecond(),
        ]);

        $this->assertTrue($invite->isOpen());
        $this->assertTrue($invite->isExpired());
        $this->assertFalse($invite->isPending());
    }

    public function test_open_invite_before_deadline_is_pending(): void
    {
        $invite = new TeamInvite([
            'status' => TeamInvite::STATUS_PENDING,
            'expires_at' => now()->addSeconds(15),
        ]);

        $this->assertTrue($invite->isOpen());
        $this->assertFalse($invite->isExpired());
        $this->assertTrue($invite->isPending());
    }
}
