<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'username',
        'name',
        'email',
        'mobile',
        'password',
        'cod_id',
        'bank_card_number',
        'bank_account_name',
        'cod_id_changed',
        'kills',
        'game_login_info',
        'wins',
        'losses',
        'wallet',
        'referral_code',
        'referred_by',
        'is_admin',
        'is_seat_admin',
        'first_deposit_done',
        'kyc_verified_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'is_seat_admin' => 'boolean',
        'first_deposit_done' => 'boolean',
        'kyc_verified_at' => 'datetime',
        'cod_id_changed' => 'boolean',
        'kills' => 'integer',
        'wallet' => 'decimal:2',
    ];

    public function isAdmin(): bool
    {
        return (bool) $this->is_admin;
    }

    public function isSeatAdmin(): bool
    {
        return (bool) $this->is_seat_admin;
    }

    public function canManageSeats(): bool
    {
        return $this->isAdmin() || $this->isSeatAdmin();
    }

    public function isKycVerified(): bool
    {
        return $this->kyc_verified_at !== null;
    }

    public function kycWalletCap(): int
    {
        return 1_000_000;
    }

    public function completedDepositTotal(): int
    {
        return (int) $this->transactions()
            ->where('type', 'deposit')
            ->where('status', 'completed')
            ->sum('amount');
    }

    public function remainingKycDepositCap(): int
    {
        if ($this->isKycVerified()) {
            return 50_000_000;
        }

        return max(0, $this->kycWalletCap() - $this->completedDepositTotal());
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }

    public function registrations()
    {
        return $this->hasMany(Registration::class);
    }

    public function kycSubmissions()
    {
        return $this->hasMany(KycSubmission::class);
    }

    public function latestKycSubmission()
    {
        return $this->hasOne(KycSubmission::class)->latestOfMany();
    }

    public function referrer()
    {
        return $this->belongsTo(self::class, 'referred_by');
    }

    public function referrals()
    {
        return $this->hasMany(self::class, 'referred_by');
    }

    public static function generateReferralCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (self::where('referral_code', $code)->exists());

        return $code;
    }

    public static function findByLogin(string $login): ?self
    {
        $login = trim($login);
        if ($login === '') {
            return null;
        }

        return self::query()
            ->where(function ($query) use ($login) {
                $query->where('email', $login)
                    ->orWhere('mobile', $login)
                    ->orWhere('username', $login);
            })
            ->first();
    }

    public static function normalizeCodIdForStorage(?string $value): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));

        return $value === '' ? null : $value;
    }

    public static function normalizeCodIdKey(?string $value): ?string
    {
        $value = self::normalizeCodIdForStorage($value);

        return $value === null ? null : mb_strtolower($value);
    }

    public static function normalizeUsernameKey(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : mb_strtolower($value);
    }

    public static function asciiDigits(string $value): string
    {
        return strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    /** @param  \Illuminate\Database\Eloquent\Builder<self>  $query */
    public function scopeMatchingAdminSearch($query, string $term)
    {
        $term = trim($term);
        if ($term === '') {
            return $query;
        }

        $ascii = trim(self::asciiDigits($term));
        $compact = preg_replace('/\s+/u', '', $ascii) ?: $ascii;
        $needle = mb_strtolower($ascii);
        $like = '%' . addcslashes($ascii, '%_\\') . '%';
        $likeLower = '%' . addcslashes($needle, '%_\\') . '%';

        return $query->where(function ($q) use ($ascii, $compact, $like, $likeLower, $needle) {
            $q->where('username', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('mobile', 'like', $like)
                ->orWhere('cod_id', 'like', $like)
                ->orWhere('referral_code', 'like', $like)
                ->orWhereRaw('LOWER(TRIM(username)) LIKE ?', [$likeLower])
                ->orWhereRaw('LOWER(TRIM(cod_id)) LIKE ?', [$likeLower])
                ->orWhereRaw('LOWER(TRIM(name)) LIKE ?', [$likeLower]);

            if (preg_match('/^\d+$/', $compact)) {
                $q->orWhere('id', (int) $compact);

                foreach (self::mobileSearchVariants($compact) as $mobile) {
                    $q->orWhere('mobile', 'like', '%' . addcslashes($mobile, '%_\\') . '%');
                }
            }

            $codKey = self::normalizeCodIdKey($ascii);
            if ($codKey !== null && $codKey !== $needle) {
                $q->orWhereRaw('LOWER(TRIM(cod_id)) LIKE ?', ['%' . addcslashes($codKey, '%_\\') . '%']);
            }
        });
    }

    /** @return list<string> */
    public static function mobileSearchVariants(string $digits): array
    {
        $digits = ltrim(preg_replace('/\D+/', '', self::asciiDigits($digits)) ?: '', '+');
        if ($digits === '') {
            return [];
        }

        $variants = [$digits];

        if (str_starts_with($digits, '98') && strlen($digits) >= 12) {
            $national = '0' . substr($digits, 2);
            $withoutZero = substr($digits, 2);
            $variants[] = $national;
            $variants[] = $withoutZero;
        } elseif (str_starts_with($digits, '0') && strlen($digits) >= 10) {
            $withoutZero = ltrim($digits, '0');
            $variants[] = $withoutZero;
            $variants[] = '98' . $withoutZero;
        } elseif (str_starts_with($digits, '9') && strlen($digits) === 10) {
            $variants[] = '0' . $digits;
            $variants[] = '98' . $digits;
        }

        return array_values(array_unique(array_filter($variants)));
    }

    public static function usernameIsTaken(?string $username, ?int $exceptUserId = null): bool
    {
        $normalized = self::normalizeUsernameKey($username);
        if ($normalized === null) {
            return false;
        }

        return self::query()
            ->whereNotNull('username')
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->whereRaw('LOWER(TRIM(username)) = ?', [$normalized])
            ->exists();
    }

    public static function codIdIsTaken(?string $codId, ?int $exceptUserId = null): bool
    {
        $normalized = self::normalizeCodIdKey($codId);
        if ($normalized === null) {
            return false;
        }

        return self::query()
            ->whereNotNull('cod_id')
            ->when($exceptUserId, fn ($query) => $query->where('id', '!=', $exceptUserId))
            ->whereRaw('LOWER(TRIM(cod_id)) = ?', [$normalized])
            ->exists();
    }

    public function refreshWalletLock(): void
    {
        if (DB::transactionLevel() === 0) {
            return;
        }

        $locked = static::query()->whereKey($this->id)->lockForUpdate()->first();
        if ($locked) {
            $this->wallet = $locked->wallet;
            $this->first_deposit_done = $locked->first_deposit_done;
        }
    }

    public function creditWallet(float $amount, string $type, string $description, ?string $referenceId = null): void
    {
        if ($referenceId) {
            $alreadyCredited = $this->transactions()
                ->where('reference_id', $referenceId)
                ->where('type', $type)
                ->where('status', 'completed')
                ->exists();

            if ($alreadyCredited) {
                return;
            }
        }

        $this->refreshWalletLock();

        $this->wallet = round($this->wallet + $amount, 2);
        $this->save();

        $this->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $this->wallet,
            'description' => $description,
            'reference_id' => $referenceId,
            'status' => 'completed',
        ]);
    }

    public function debitWallet(float $amount, string $type, string $description, ?string $referenceId = null, bool $allowNegative = false): void
    {
        if ($referenceId) {
            $alreadyDebited = $this->transactions()
                ->where('reference_id', $referenceId)
                ->where('type', $type)
                ->where('status', 'completed')
                ->exists();

            if ($alreadyDebited) {
                return;
            }
        }

        $this->refreshWalletLock();

        if (! $allowNegative && $this->wallet < $amount) {
            throw new \InvalidArgumentException('موجودی کیف پول کافی نیست.');
        }

        $this->wallet = round($this->wallet - $amount, 2);
        $this->save();

        $this->transactions()->create([
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $this->wallet,
            'description' => $description,
            'reference_id' => $referenceId,
            'status' => 'completed',
        ]);
    }
}