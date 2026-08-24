<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Registration extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'tournament_id',
        'status',
        'reservation_type',
        'seat_number',
        'rank',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function hasConfirmedSeat(): bool
    {
        return $this->seat_number !== null;
    }
}
