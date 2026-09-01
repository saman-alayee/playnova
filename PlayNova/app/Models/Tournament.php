<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Tournament extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'game',
        'league',
        'description',
        'entry_fee',
        'prize_pool',
        'prize_ranks',
        'capacity',
        'seat_mode',
        'registered_count',
        'start_date',
        'end_date',
        'status',
        'winner_id',
        'game_login_info',
        'result_ai_system_prompt',
        'result_ai_user_prompt',
    ];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'entry_fee' => 'integer',
        'prize_pool' => 'integer',
        'prize_ranks' => 'array',
        'capacity' => 'integer',
        'seat_mode' => 'integer',
        'registered_count' => 'integer',
    ];

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function players()
    {
        return $this->belongsToMany(User::class, 'registrations');
    }

    public function winner()
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function confirmedRegistrationsCount(): int
    {
        if ($this->registered_count !== null) {
            return (int) $this->registered_count;
        }

        return $this->registrations()->whereNotNull('seat_number')->count();
    }

    public function getRemainingCapacityAttribute(): int
    {
        return max(0, $this->capacity - (int) $this->registered_count);
    }

    public function isFull(): bool
    {
        return $this->confirmedRegistrationsCount() >= (int) $this->capacity;
    }

    /** Teammate invites required: solo=0, duo=1, squad=3 */
    public function requiredTeammateInvites(): int
    {
        return max(0, $this->seatMode() - 1);
    }

    public function supportsTeamInvite(): bool
    {
        return $this->seatMode() >= 2;
    }

    public const GAME_LOGIN_PLACEHOLDER = 'اطلاعات ورود به مسابقه، رأس ساعت برگزاری از طریق همین بخش نمایش داده خواهد شد.';

    public function hasPublishedGameLogin(): bool
    {
        return filled(trim((string) ($this->game_login_info ?? '')));
    }

    public function gameLoginMessage(): string
    {
        $info = trim((string) ($this->game_login_info ?? ''));

        if ($info !== '') {
            return $info;
        }

        return self::GAME_LOGIN_PLACEHOLDER;
    }

    public static function statusLabels(): array
    {
        return [
            'upcoming' => 'آینده',
            'active' => 'فعال',
            'ongoing' => 'در حال برگزاری',
            'ended' => 'پایان‌یافته',
            'cancelled' => 'لغو شده',
        ];
    }

    public function statusLabel(): string
    {
        return self::statusLabels()[$this->status] ?? $this->status;
    }

    public function acceptsRegistration(): bool
    {
        return $this->status === 'active';
    }

    public function allowsGameLogin(): bool
    {
        return in_array($this->status, ['active', 'ongoing'], true);
    }

    public function seatMode(): int
    {
        $mode = (int) ($this->seat_mode ?? 1);

        return in_array($mode, [1, 2, 4], true) ? $mode : 1;
    }

    public function seatModeLabel(): string
    {
        return match ($this->seatMode()) {
            2 => 'دو نفره',
            4 => 'چهار نفره',
            default => 'یک نفره',
        };
    }

    public function seatNumbers(): array
    {
        return range(1, max(1, (int) $this->capacity));
    }

    public function teamCount(): int
    {
        $mode = max(1, $this->seatMode());

        return (int) ceil(max(1, (int) $this->capacity) / $mode);
    }

    public function seatDisplayLabel(int $seatNumber): string
    {
        $mode = max(1, $this->seatMode());
        $team = (int) ceil($seatNumber / $mode);
        $slot = (($seatNumber - 1) % $mode) + 1;

        return $team . '.' . $slot;
    }

    public function teamNumberForSeat(?int $seatNumber): ?int
    {
        if (! $seatNumber || $seatNumber < 1) {
            return null;
        }

        return (int) ceil($seatNumber / max(1, $this->seatMode()));
    }

    /** @return list<array{team:int, slots:list<array{seat_number:int, label:string, slot:int}>}> */
    public function teamsForGrid(): array
    {
        $mode = max(1, $this->seatMode());
        $teams = [];

        for ($team = 1; $team <= $this->teamCount(); $team++) {
            $slots = [];
            for ($slot = 1; $slot <= $mode; $slot++) {
                $seatNumber = ($team - 1) * $mode + $slot;
                if ($seatNumber > (int) $this->capacity) {
                    break;
                }
                $slots[] = [
                    'seat_number' => $seatNumber,
                    'label' => $this->seatDisplayLabel($seatNumber),
                    'slot' => $slot,
                ];
            }
            if ($slots !== []) {
                $teams[] = ['team' => $team, 'slots' => $slots];
            }
        }

        return $teams;
    }

    /**
     * Rank => amount in Toman. Empty means admin has not set a split yet.
     *
     * @return array<int, float>
     */
    public function prizeRanksTable(): array
    {
        return self::normalizePrizeRanks($this->prize_ranks);
    }

    /**
     * @return list<array{rank:int,amount:float}>
     */
    public function prizeRanksList(): array
    {
        $rows = [];
        foreach ($this->prizeRanksTable() as $rank => $amount) {
            $rows[] = ['rank' => $rank, 'amount' => $amount];
        }

        return $rows;
    }

    public function prizeAmountForRank(int $rank): float
    {
        return (float) ($this->prizeRanksTable()[$rank] ?? 0);
    }

    /**
     * @param  mixed  $input
     * @return array<int, float>
     */
    public static function normalizePrizeRanks(mixed $input): array
    {
        if (! is_array($input) || $input === []) {
            return [];
        }

        $table = [];

        $isList = array_is_list($input);
        foreach ($input as $key => $value) {
            if ($isList && is_array($value)) {
                $rank = (int) ($value['rank'] ?? 0);
                $amount = (float) ($value['amount'] ?? 0);
            } else {
                $rank = (int) $key;
                $amount = is_array($value) ? (float) ($value['amount'] ?? 0) : (float) $value;
            }

            if ($rank < 1 || $rank > 100 || $amount < 0) {
                continue;
            }

            if ($amount <= 0) {
                continue;
            }

            $table[$rank] = round($amount, 0);
        }

        ksort($table);

        return $table;
    }
}