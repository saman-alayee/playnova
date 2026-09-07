<?php

namespace App\Support;

class PlacementRankCompactor
{
    /**
     * Re-number prize ranks in list order so removed teams shift everyone up.
     *
     * Rows that share the same original rank stay teammates. The first time a
     * rank appears becomes placement 1, the next distinct rank becomes 2, etc.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function compact(array $rows): array
    {
        $map = [];
        $next = 1;

        foreach ($rows as $row) {
            $rank = (int) ($row['rank'] ?? 0);
            if ($rank < 1) {
                continue;
            }
            if (! isset($map[$rank])) {
                $map[$rank] = $next++;
            }
        }

        foreach ($rows as $index => $row) {
            $rank = (int) ($row['rank'] ?? 0);
            if ($rank < 1) {
                continue;
            }
            $rows[$index]['rank'] = $map[$rank];
        }

        return $rows;
    }
}
