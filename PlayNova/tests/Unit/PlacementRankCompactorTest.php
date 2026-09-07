<?php

namespace Tests\Unit;

use App\Support\PlacementRankCompactor;
use PHPUnit\Framework\TestCase;

class PlacementRankCompactorTest extends TestCase
{
    public function test_removing_a_middle_team_shifts_later_ranks_up(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1],
            ['user_id' => 2, 'rank' => 1],
            ['user_id' => 5, 'rank' => 3],
            ['user_id' => 6, 'rank' => 3],
            ['user_id' => 7, 'rank' => 4],
            ['user_id' => 8, 'rank' => 4],
        ]);

        $this->assertSame([1, 1, 2, 2, 3, 3], array_column($rows, 'rank'));
    }

    public function test_reordering_a_team_to_the_top_updates_prizes_by_list_order(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 7, 'rank' => 4],
            ['user_id' => 8, 'rank' => 4],
            ['user_id' => 1, 'rank' => 1],
            ['user_id' => 2, 'rank' => 1],
            ['user_id' => 3, 'rank' => 2],
            ['user_id' => 4, 'rank' => 2],
        ]);

        $this->assertSame([1, 1, 2, 2, 3, 3], array_column($rows, 'rank'));
    }

    public function test_already_compact_ranks_stay_unchanged(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['rank' => 1],
            ['rank' => 2],
            ['rank' => 3],
        ]);

        $this->assertSame([1, 2, 3], array_column($rows, 'rank'));
    }

    public function test_rows_without_rank_are_left_unranked(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['rank' => 2],
            ['rank' => null],
            ['rank' => 5],
        ]);

        $this->assertSame(1, $rows[0]['rank']);
        $this->assertNull($rows[1]['rank']);
        $this->assertSame(2, $rows[2]['rank']);
    }
}
