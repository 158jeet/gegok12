<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecoveryChallenge extends Model
{
    protected $table = 'recovery_challenges';

    protected $fillable = [
        'user_id',
        'usergroup_id',
        'purpose',
        'channel',
        'code_hash',
        'ip_address',
        'expires_at',
        'attempt_count',
        'verified_at',
        'consumed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'consumed_at' => 'datetime',
        'attempt_count' => 'integer',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
