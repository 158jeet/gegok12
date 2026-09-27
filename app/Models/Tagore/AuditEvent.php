<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class AuditEvent extends Model
{
    protected $table = 'tagore_audit_events';
    protected $guarded = [];
    protected $casts = ['old_values_json' => 'array', 'new_values_json' => 'array'];
}
