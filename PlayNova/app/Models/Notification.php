<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $fillable = [
        'user_id',
        'title',
        'message',
        'type',
        'broadcast_group_id',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> */
    public static function teamInviteTypes(): array
    {
        return [
            'team_invite',
            'team_invite_accepted',
            'team_invite_declined',
            'team_invite_cancelled',
            'team_invite_failed',
        ];
    }

    public function scopeVisibleInInbox($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('type')
                ->orWhereNotIn('type', self::teamInviteTypes());
        });
    }
}