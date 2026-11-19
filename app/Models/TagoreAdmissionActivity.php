<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagoreAdmissionActivity extends Model
{
    protected $table = 'tagore_admission_activities';

    protected $fillable = ['lead_id','user_id','type','outcome','notes','scheduled_at','completed_at'];

    protected $casts = ['scheduled_at'=>'datetime','completed_at'=>'datetime'];

    public function lead()
    {
        return $this->belongsTo(TagoreAdmissionLead::class, 'lead_id');
    }
}
