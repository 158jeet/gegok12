<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagoreCrmTelephonyAgent extends Model
{
    protected $table = 'tagore_crm_telephony_agents';

    protected $fillable = [
        'institution_id', 'telephony_account_id', 'user_id',
        'agent_number', 'is_active', 'created_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function account()
    {
        return $this->belongsTo(TagoreCrmTelephonyAccount::class, 'telephony_account_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
