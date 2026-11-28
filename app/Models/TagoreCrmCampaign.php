<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmCampaign extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_campaigns';

    protected $fillable = [
        'institution_id', 'name', 'campaign_code', 'channel', 'status',
        'objective', 'budget', 'spend', 'starts_on', 'ends_on',
        'landing_url', 'utm_source', 'utm_medium', 'utm_campaign',
        'owner_user_id', 'created_by',
    ];

    protected $casts = [
        'budget' => 'decimal:2',
        'spend' => 'decimal:2',
        'starts_on' => 'date',
        'ends_on' => 'date',
    ];
}
