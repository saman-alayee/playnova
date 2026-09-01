<?php

namespace Tests\Unit;

use App\Services\TournamentResultVisionService;
use PHPUnit\Framework\TestCase;

class TournamentResultTeamNumberTest extends TestCase
{
    public function test_parses_codm_team_labels(): void
    {
        $this->assertSame(11, TournamentResultVisionService::teamNumberFromLabel('TEAM11'));
        $this->assertSame(18, TournamentResultVisionService::teamNumberFromLabel('team 18'));
        $this->assertSame(6, TournamentResultVisionService::teamNumberFromLabel('#6'));
        $this->assertSame(3, TournamentResultVisionService::teamNumberFromLabel('تیم ۳', null));
        $this->assertSame(12, TournamentResultVisionService::teamNumberFromLabel(null, 12));
    }
}
