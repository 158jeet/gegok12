<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagoreCrmCall extends Model
{
    protected $table = 'tagore_crm_calls';

    protected $fillable = [
        'institution_id', 'telephony_account_id', 'lead_id', 'provider_call_id',
        'direction', 'from_number', 'to_number', 'status', 'disposition',
        'duration_seconds', 'started_at', 'answered_at', 'ended_at',
        'recording_url', 'provider_payload', 'initiated_by',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'answered_at' => 'datetime',
        'ended_at' => 'datetime',
        'provider_payload' => 'array',
    ];
}
