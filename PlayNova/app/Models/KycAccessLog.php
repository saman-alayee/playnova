<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KycAccessLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'admin_id',
        'kyc_submission_id',
        'action',
        'ip_address',
        'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function submission()
    {
        return $this->belongsTo(KycSubmission::class, 'kyc_submission_id');
    }
}
