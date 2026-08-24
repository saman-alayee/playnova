<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_after',
        'description',
        'reference_id',
        'status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function displayedAt(): Carbon
    {
        if ($this->type === 'withdraw' && in_array($this->status, ['completed', 'rejected'], true)) {
            return $this->updated_at;
        }

        return $this->created_at;
    }

    public function rejectionReason(): ?string
    {
        if ($this->status !== 'rejected') {
            return null;
        }

        if (preg_match('/\|\s*دلیل:\s*(.+)$/u', (string) $this->description, $matches)) {
            return trim($matches[1]);
        }

        return null;
    }
}