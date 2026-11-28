<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmOpportunity extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_opportunities';

    protected $fillable = [
        'institution_id', 'lead_id', 'assigned_to', 'created_by', 'name',
        'opportunity_type', 'stage', 'status', 'amount', 'probability',
        'expected_close_date', 'source', 'campaign', 'lost_reason',
        'converted_student_id', 'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'probability' => 'integer',
        'expected_close_date' => 'date',
    ];

    public function lead()
    {
        return $this->belongsTo(TagoreAdmissionLead::class, 'lead_id');
    }
}
