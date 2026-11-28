<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmCase extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_cases';

    protected $fillable = [
        'institution_id', 'lead_id', 'account_id', 'contact_id', 'student_id', 'parent_user_id',
        'assigned_to', 'created_by', 'subject', 'category', 'priority',
        'status', 'due_at', 'resolved_at', 'description', 'resolution',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'resolved_at' => 'datetime',
    ];

    public function lead()
    {
        return $this->belongsTo(TagoreAdmissionLead::class, 'lead_id');
    }

    public function account()
    {
        return $this->belongsTo(TagoreCrmAccount::class, 'account_id');
    }

    public function contact()
    {
        return $this->belongsTo(TagoreCrmContact::class, 'contact_id');
    }

    public function student()
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function parent()
    {
        return $this->belongsTo(User::class, 'parent_user_id');
    }

    public function assignee()
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
