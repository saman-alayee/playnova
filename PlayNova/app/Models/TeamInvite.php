<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TeamInvite extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'tournament_id',
        'inviter_id',
        'invitee_id',
        'status',
        'failure_reason',
        'seat_number_inviter',
        'seat_number_invitee',
    ];

    protected $casts = [
        'inviter_id' => 'integer',
        'invitee_id' => 'integer',
        'tournament_id' => 'integer',
        'seat_number_inviter' => 'integer',
        'seat_number_invitee' => 'integer',
    ];

    public function tournament()
    {
        return $this->belongsTo(Tournament::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id');
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
