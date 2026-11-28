<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmTask extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_tasks';

    protected $fillable = [
        'institution_id', 'lead_id', 'opportunity_id', 'account_id', 'contact_id',
        'call_id', 'assigned_to', 'created_by', 'title', 'task_type', 'priority',
        'status', 'due_at', 'completed_at', 'outcome', 'description',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(TagoreAdmissionLead::class, 'lead_id');
    }

    public function opportunity()
    {
        return $this->belongsTo(TagoreCrmOpportunity::class, 'opportunity_id');
    }

    public function account()
    {
        return $this->belongsTo(TagoreCrmAccount::class, 'account_id');
    }

    public function contact()
    {
        return $this->belongsTo(TagoreCrmContact::class, 'contact_id');
    }

    public function call()
    {
        return $this->belongsTo(TagoreCrmCall::class, 'call_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
