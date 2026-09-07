<?php

namespace Tests\Unit;

use App\Models\Tournament;
use App\Services\TournamentResultVisionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TournamentResultMatchTeamsTest extends TestCase
{
    public function test_does_not_add_registered_teammate_missing_from_leaderboard(): void
    {
        $service = (new \ReflectionClass(TournamentResultVisionService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(TournamentResultVisionService::class, 'matchTeams');
        $method->setAccessible(true);

        $result = $method->invoke($service, [
            [
                'rank' => 1,
                'team_number' => 3,
                'team_label' => 'TEAM3',
                'player_names' => ['OnlyOne'],
                'uids' => [null],
                'kills' => [5],
            ],
        ], [
            [
                'user_id' => 10,
                'username' => 'present',
                'cod_id' => 'OnlyOne',
                'seat_number' => 5,
                'team_number' => 3,
            ],
            [
                'user_id' => 11,
                'username' => 'absent',
                'cod_id' => 'MissingTeammate',
                'seat_number' => 6,
                'team_number' => 3,
            ],
        ], new Tournament(['seat_mode' => 2, 'capacity' => 10]));

        $this->assertCount(1, $result['matched']);
        $this->assertSame(10, $result['matched'][0]['user_id']);
    }
}
