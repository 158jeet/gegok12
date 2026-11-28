<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmContact extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_contacts';

    protected $fillable = [
        'institution_id', 'account_id', 'lead_id', 'linked_user_id', 'first_name',
        'last_name', 'full_name', 'title', 'department', 'contact_type', 'email',
        'phone', 'alternate_phone', 'preferred_channel', 'contact_consent',
        'owner_user_id', 'created_by', 'status', 'notes',
    ];

    protected $casts = ['contact_consent' => 'boolean'];

    public function account()
    {
        return $this->belongsTo(TagoreCrmAccount::class, 'account_id');
    }

    public function lead()
    {
        return $this->belongsTo(TagoreAdmissionLead::class, 'lead_id');
    }

    public function linkedUser()
    {
        return $this->belongsTo(User::class, 'linked_user_id');
    }

    public function opportunities()
    {
        return $this->hasMany(TagoreCrmOpportunity::class, 'contact_id');
    }

    public function tasks()
    {
        return $this->hasMany(TagoreCrmTask::class, 'contact_id');
    }

    public function cases()
    {
        return $this->hasMany(TagoreCrmCase::class, 'contact_id');
    }
}
