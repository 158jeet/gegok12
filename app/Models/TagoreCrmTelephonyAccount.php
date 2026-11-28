<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagoreCrmTelephonyAccount extends Model
{
    protected $table = 'tagore_crm_telephony_accounts';

    protected $fillable = [
        'institution_id', 'provider', 'account_sid', 'credentials',
        'caller_id', 'record_calls', 'webhook_token_hash', 'is_active', 'created_by',
    ];

    protected $casts = [
        'credentials' => 'encrypted:array',
        'is_active' => 'boolean',
        'record_calls' => 'boolean',
    ];
}
