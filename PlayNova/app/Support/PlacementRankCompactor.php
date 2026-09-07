<?php

namespace App\Support;

class PlacementRankCompactor
{
    /**
     * Re-number prize ranks in list order so removed teams shift everyone up,
     * including teams that were below the original prize cutoff.
     *
     * Rows that share the same original rank stay teammates. Trailing rows
     * without a rank are grouped by seat-team and receive the next placements.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function compact(array $rows, int $seatMode = 1): array
    {
        $seatMode = max(1, $seatMode);
        $rankMap = [];
        $unrankedTeamMap = [];
        $next = 1;

        foreach ($rows as $index => $row) {
            $rank = (int) ($row['rank'] ?? 0);
            if ($rank >= 1) {
                if (! isset($rankMap[$rank])) {
                    $rankMap[$rank] = $next++;
                }
                $rows[$index]['rank'] = $rankMap[$rank];
                continue;
            }

            $seat = (int) ($row['seat_number'] ?? 0);
            $teamKey = $seat >= 1 ? 't-' . (int) ceil($seat / $seatMode) : 'i-' . $index;
            if (! isset($unrankedTeamMap[$teamKey])) {
                $unrankedTeamMap[$teamKey] = $next++;
            }
            $rows[$index]['rank'] = $unrankedTeamMap[$teamKey];
        }

        return $rows;
    }
}
