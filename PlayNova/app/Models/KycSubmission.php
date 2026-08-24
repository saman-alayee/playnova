<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycSubmission extends Model
{
    protected $fillable = [
        'user_id',
        'national_id_encrypted',
        'card_front_path',
        'card_back_path',
        'document_path',
        'status',
        'reviewed_at',
        'admin_note',
    ];

    protected $casts = [
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function accessLogs()
    {
        return $this->hasMany(KycAccessLog::class);
    }
}
