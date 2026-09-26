<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KoboSubmission extends Model
{
    protected $fillable = [
        'asset_uid',
        'submission_uid',
        'submission_id',
        'submitted_at',
        'device_id',
        'submitted_by',
        'data',
        'synced_at',
    ];

    protected $casts = [
        'data' => 'array',
        'submitted_at' => 'datetime',
        'synced_at' => 'datetime',
    ];
}