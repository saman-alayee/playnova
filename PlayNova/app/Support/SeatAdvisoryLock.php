<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

final class SeatAdvisoryLock
{
    /**
     * Serialize assignment of the given seats without locking the whole tournament row.
     *
     * @param  list<int>  $seatNumbers
     */
    public static function run(int $tournamentId, array $seatNumbers, callable $callback): mixed
    {
        $seats = array_values(array_unique(array_map('intval', $seatNumbers)));
        sort($seats);

        if ($seats === [] || ! self::driverSupportsAdvisoryLocks()) {
            return $callback();
        }

        $names = array_map(
            fn (int $seat) => sprintf('pn:s:%d:%d', $tournamentId, $seat),
            $seats
        );

        try {
            foreach ($names as $name) {
                if (self::getLock($name, 10) !== 1) {
                    throw new RuntimeException('seat_taken');
                }
            }

            return $callback();
        } finally {
            foreach (array_reverse($names) as $name) {
                self::releaseLock($name);
            }
        }
    }

    public static function driverSupportsAdvisoryLocks(): bool
    {
        return in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true);
    }

    private static function getLock(string $name, int $timeout): int
    {
        $row = DB::selectOne('SELECT GET_LOCK(?, ?) AS g', [$name, $timeout]);

        return (int) ($row->g ?? 0);
    }

    private static function releaseLock(string $name): void
    {
        try {
            DB::select('SELECT RELEASE_LOCK(?)', [$name]);
        } catch (Throwable) {
            // Connection-level lock; never let release errors hide the original failure.
        }
    }
}
