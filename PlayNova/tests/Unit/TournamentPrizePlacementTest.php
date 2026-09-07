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

    private function invoke(string $method, ...$args): mixed
    {
        $fn = (new ReflectionClass(TournamentPrizeService::class))->getMethod($method);
        $fn->setAccessible(true);

        return $fn->invoke($this->service(), ...$args);
    }

    public function test_deleted_middle_team_receives_next_prize_amount(): void
    {
        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 8]);
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1, 'kills' => 5, 'team_label' => '1.1', 'seat_number' => 1, 'confirmation_status' => 'confirmed'],
            ['user_id' => 2, 'rank' => 1, 'kills' => 3, 'team_label' => '1.2', 'seat_number' => 2, 'confirmation_status' => 'confirmed'],
            ['user_id' => 5, 'rank' => 3, 'kills' => 2, 'team_label' => '3.1', 'seat_number' => 5, 'confirmation_status' => 'confirmed'],
            ['user_id' => 6, 'rank' => 3, 'kills' => 1, 'team_label' => '3.2', 'seat_number' => 6, 'confirmation_status' => 'confirmed'],
        ], 2);

        $result = $this->invoke('assignPrizeAmounts', $tournament, $rows, [
            1 => 1_000_000.0,
            2 => 500_000.0,
            3 => 200_000.0,
        ]);

        $byUser = collect($result)->keyBy('user_id');
        $this->assertSame(1, $byUser[1]['rank']);
        $this->assertSame(2, $byUser[5]['rank']);
        $this->assertSame(500_000.0, $byUser[5]['prize_amount'] + $byUser[6]['prize_amount']);
        $this->assertSame(1_000_000.0, $byUser[1]['prize_amount'] + $byUser[2]['prize_amount']);
    }

    public function test_teams_below_cutoff_inherit_vacated_prize_ranks(): void
    {
        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 24]);
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1, 'seat_number' => 1, 'confirmation_status' => 'confirmed'],
            ['user_id' => 2, 'rank' => 1, 'seat_number' => 2, 'confirmation_status' => 'confirmed'],
            ['user_id' => 9, 'rank' => 5, 'seat_number' => 9, 'confirmation_status' => 'confirmed'],
            ['user_id' => 10, 'rank' => 5, 'seat_number' => 10, 'confirmation_status' => 'confirmed'],
            ['user_id' => 21, 'rank' => 11, 'seat_number' => 21, 'confirmation_status' => 'confirmed'],
            ['user_id' => 22, 'rank' => 11, 'seat_number' => 22, 'confirmation_status' => 'confirmed'],
        ], 2);

        $result = $this->invoke('assignPrizeAmounts', $tournament, $rows, [
            1 => 1_000_000.0,
            2 => 500_000.0,
            3 => 200_000.0,
        ]);

        $byUser = collect($result)->keyBy('user_id');
        $this->assertSame(2, $byUser[9]['rank']);
        $this->assertSame(3, $byUser[21]['rank']);
        $this->assertSame(500_000.0, $byUser[9]['prize_amount'] + $byUser[10]['prize_amount']);
        $this->assertSame(200_000.0, $byUser[21]['prize_amount'] + $byUser[22]['prize_amount']);
    }

    public function test_solo_present_player_gets_half_of_duo_prize(): void
    {
        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 4]);
        $rows = [
            [
                'user_id' => 10,
                'rank' => 1,
                'kills' => 4,
                'team_label' => '1.1',
                'seat_number' => 1,
                'on_leaderboard' => true,
                'confirmation_status' => 'confirmed',
            ],
            [
                'user_id' => 11,
                'rank' => 1,
                'kills' => null,
                'team_label' => '1.2',
                'seat_number' => 2,
                'on_leaderboard' => false,
                'confirmation_status' => 'unconfirmed',
                'confirmation_reason' => 'not_on_leaderboard',
            ],
        ];

        $result = $this->invoke('assignPrizeAmounts', $tournament, $rows, [
            1 => 500_000.0,
        ]);

        $byUser = collect($result)->keyBy('user_id');
        $this->assertSame(250_000.0, $byUser[10]['prize_amount']);
        $this->assertSame(0.0, $byUser[11]['prize_amount']);
        $this->assertSame('unconfirmed', $byUser[11]['metadata']['confirmation_status']);
    }

    public function test_append_absent_teammate_as_unconfirmed_zero(): void
    {
        $tournament = new Tournament(['seat_mode' => 2, 'capacity' => 4]);
        $registrations = new Collection([
            10 => (object) ['user_id' => 10, 'seat_number' => 1],
            11 => (object) ['user_id' => 11, 'seat_number' => 2],
        ]);

        $result = $this->invoke('appendAbsentTeammates', $tournament, [
            [
                'user_id' => 10,
                'rank' => 1,
                'kills' => 4,
                'team_label' => '1.1',
                'seat_number' => 1,
                'confirmation_status' => 'confirmed',
            ],
        ], $registrations);

        $this->assertCount(2, $result);
        $absent = collect($result)->firstWhere('user_id', 11);
        $this->assertSame('unconfirmed', $absent['confirmation_status']);
        $this->assertSame('not_on_leaderboard', $absent['confirmation_reason']);
        $this->assertSame(1, $absent['rank']);
    }
}
