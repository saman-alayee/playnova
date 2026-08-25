<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TournamentPrizeEntry extends Model
{
    protected $fillable = [
        'batch_id',
        'user_id',
        'rank',
        'team_label',
        'seat_number',
        'prize_amount',
        'kills',
        'metadata',
    ];

    protected $casts = [
        'rank' => 'integer',
        'seat_number' => 'integer',
        'prize_amount' => 'decimal:2',
        'kills' => 'integer',
        'metadata' => 'array',
    ];

    public function batch(): BelongsTo
    {
        return $this->belongsTo(TournamentPrizeBatch::class, 'batch_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
