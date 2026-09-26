<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreAdmissionLead extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_admission_leads';

    protected $fillable = [
        'institution_id','academic_year_id','lead_no','student_name','parent_name',
        'mobile','alternate_mobile','email','class_name','source','campaign','status','lost_reason','converted_at','last_contacted_at',
        'assigned_to','next_follow_up_at','notes',
    ];

    protected $casts = ['next_follow_up_at'=>'datetime','converted_at'=>'datetime','last_contacted_at'=>'datetime'];

    public function activities()
    {
        return $this->hasMany(TagoreAdmissionActivity::class, 'lead_id');
    }
}
