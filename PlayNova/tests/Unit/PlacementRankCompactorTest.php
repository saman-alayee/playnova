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

    public function test_teams_below_prize_cutoff_enter_vacated_ranks(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1, 'seat_number' => 1],
            ['user_id' => 2, 'rank' => 1, 'seat_number' => 2],
            ['user_id' => 3, 'rank' => 2, 'seat_number' => 3],
            ['user_id' => 4, 'rank' => 2, 'seat_number' => 4],
            ['user_id' => 9, 'rank' => 5, 'seat_number' => 9],
            ['user_id' => 10, 'rank' => 5, 'seat_number' => 10],
            ['user_id' => 13, 'rank' => 7, 'seat_number' => 13],
            ['user_id' => 14, 'rank' => 7, 'seat_number' => 14],
            ['user_id' => 21, 'rank' => 11, 'seat_number' => 21],
            ['user_id' => 22, 'rank' => 11, 'seat_number' => 22],
            ['user_id' => 23, 'rank' => 12, 'seat_number' => 23],
            ['user_id' => 24, 'rank' => 12, 'seat_number' => 24],
        ], 2);

        $this->assertSame([1, 1, 2, 2, 3, 3, 4, 4, 5, 5, 6, 6], array_column($rows, 'rank'));
    }

    public function test_trailing_unranked_teams_fill_deleted_prize_slots(): void
    {
        $rows = PlacementRankCompactor::compact([
            ['user_id' => 1, 'rank' => 1, 'seat_number' => 1],
            ['user_id' => 2, 'rank' => 1, 'seat_number' => 2],
            ['user_id' => 5, 'rank' => 5, 'seat_number' => 5],
            ['user_id' => 6, 'rank' => 5, 'seat_number' => 6],
            ['user_id' => 21, 'rank' => null, 'seat_number' => 21],
            ['user_id' => 22, 'rank' => null, 'seat_number' => 22],
        ], 2);

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
}
