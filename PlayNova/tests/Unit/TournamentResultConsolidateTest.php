<?php

namespace Tests\Unit;

use App\Services\TournamentResultVisionService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class TournamentResultConsolidateTest extends TestCase
{
    /** @param  list<array<string,mixed>>  $teams */
    private function consolidate(array $teams): array
    {
        $service = (new \ReflectionClass(TournamentResultVisionService::class))->newInstanceWithoutConstructor();
        $method = new ReflectionMethod(TournamentResultVisionService::class, 'consolidateTeams');
        $method->setAccessible(true);

        return $method->invoke($service, $teams);
    }

    public function test_merges_duplicate_ranks(): void
    {
        $result = $this->consolidate([
            [
                'rank' => 2,
                'team_number' => 18,
                'team_label' => 'TEAM18',
                'player_names' => ['Alpha'],
                'uids' => [null],
                'kills' => [4],
            ],
            [
                'rank' => 2,
                'team_number' => 18,
                'team_label' => 'TEAM18',
                'player_names' => ['Beta'],
                'uids' => [null],
                'kills' => [null],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(['Alpha', 'Beta'], $result[0]['player_names']);
        $this->assertSame([4, null], $result[0]['kills']);
    }

    public function test_keeps_best_rank_for_duplicate_team_number(): void
    {
        $result = $this->consolidate([
            [
                'rank' => 5,
                'team_number' => 7,
                'team_label' => 'TEAM7',
                'player_names' => ['Late'],
                'uids' => [null],
                'kills' => [1],
            ],
            [
                'rank' => 3,
                'team_number' => 7,
                'team_label' => 'TEAM7',
                'player_names' => ['Correct'],
                'uids' => [null],
                'kills' => [2],
            ],
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(3, $result[0]['rank']);
        $this->assertSame(['Correct'], $result[0]['player_names']);
    }
}
