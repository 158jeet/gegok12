<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class HomeworkApproval
 *
 * Model for managing homework approval workflow.
 *
 * @property int $id
 * @property int $homework_id
 * @property string $comments
 * @property int $status
 * @property int $approved_by
 * @property \DateTime $approved_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property-read \App\Models\User $approvedBy
 * @property-read \App\Models\Homework $homework
 * @mixin \Eloquent
 */
class HomeworkApproval extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $table = 'homework_approvals';

    protected $fillable = [
        'homework_id' , 'comments' , 'status' , 'approved_by' , 'approved_at'
    ];

    protected $dates = ['approved_at' , 'deleted_at'];

    /**
     * Get the user who approved this homework.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function approvedBy()
    {
    	return $this->belongsTo('\App\Models\User','approved_by');
    }

    /**
     * Get the homework associated with this approval.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function homework()
    {
    	return $this->belongsTo('\App\Models\Homework','homework_id');
    }
}
