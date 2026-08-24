<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
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

    public function creditWallet(float $amount, string $type, string $description, ?string $referenceId = null): void
    {
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

    public function debitWallet(float $amount, string $type, string $description, ?string $referenceId = null): void
    {
        if ($this->wallet < $amount) {
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