<?php

namespace Tests\Unit;

use App\Models\Tournament;
use App\Modules\Tournament\Services\TournamentPrizeService;
use App\Services\TournamentPrizeTableParser;
use App\Support\PlacementRankCompactor;
use Illuminate\Support\Collection;
use ReflectionClass;
use Tests\TestCase;

class TournamentPrizePlacementTest extends TestCase
{
    private function service(): TournamentPrizeService
    {
        $ref = new ReflectionClass(TournamentPrizeService::class);
        $service = $ref->newInstanceWithoutConstructor();
        $parser = $ref->getProperty('prizeTableParser');
        $parser->setAccessible(true);
        $parser->setValue($service, new TournamentPrizeTableParser());

        return $service;
    }

    public function test_expand_teammates_does_not_readd_omitted_players(): void
    {
        $service = $this->service();
        $method = (new ReflectionClass(TournamentPrizeService::class))->getMethod('expandTeammates');
        $method->setAccessible(true);

        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 4]);
        $registrations = new Collection([
            10 => (object) ['user_id' => 10, 'seat_number' => 1],
            11 => (object) ['user_id' => 11, 'seat_number' => 2],
        ]);

        $result = $method->invoke($service, $tournament, [
            [
                'user_id' => 10,
                'rank' => 1,
                'kills' => 4,
                'team_label' => '1.1',
                'seat_number' => 1,
            ],
        ], $registrations);

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['user_id']);
    }

    public function test_deleted_middle_team_receives_next_prize_amount(): void
    {
        $service = $this->service();
        $method = (new ReflectionClass(TournamentPrizeService::class))->getMethod('assignPrizeAmounts');
        $method->setAccessible(true);

        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 8]);
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1, 'kills' => 5, 'team_label' => '1.1', 'seat_number' => 1],
            ['user_id' => 2, 'rank' => 1, 'kills' => 3, 'team_label' => '1.2', 'seat_number' => 2],
            ['user_id' => 5, 'rank' => 3, 'kills' => 2, 'team_label' => '3.1', 'seat_number' => 5],
            ['user_id' => 6, 'rank' => 3, 'kills' => 1, 'team_label' => '3.2', 'seat_number' => 6],
        ]);

        $result = $method->invoke($service, $tournament, $rows, [
            1 => 1_000_000.0,
            2 => 500_000.0,
            3 => 200_000.0,
        ]);

        $byUser = collect($result)->keyBy('user_id');
        $this->assertSame(1, $byUser[1]['rank']);
        $this->assertSame(1, $byUser[2]['rank']);
        $this->assertSame(2, $byUser[5]['rank']);
        $this->assertSame(2, $byUser[6]['rank']);
        $this->assertSame(500_000.0, $byUser[5]['prize_amount'] + $byUser[6]['prize_amount']);
        $this->assertSame(1_000_000.0, $byUser[1]['prize_amount'] + $byUser[2]['prize_amount']);
    }
}
