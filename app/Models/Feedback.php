<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Feedback
 *
 * Model for managing feedback and discussions between parents and administrators.
 *
 * @property int $id
 * @property int $school_id
 * @property int $parent_id
 * @property int $student_id
 * @property int $admin_id
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\FeedbackMessage[] $feedbackMessage
 * @property-read \App\Models\FeedbackMessage $latestMessage
 * @property-read \App\Models\User $parent
 * @property-read \App\Models\User $student
 * @property-read \App\Models\User $admin
 * @mixin \Eloquent
 */
class Feedback extends Model
{
    use SoftDeletes;

    protected $table = 'feedbacks';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id', 'parent_id' , 'student_id' , 'admin_id' , 'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get feedback messages for this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function feedbackMessage()
    {
        return $this->hasMany('App\Models\FeedbackMessage','feedback_id','id');
    }

    /**
     * Get the latest message for this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function latestMessage()
    {
        return $this->hasOne('App\Models\FeedbackMessage','feedback_id','id')->orderByDesc('id')->limit(1);
    }

    /**
     * Get the parent who initiated this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function parent()
    {
        return $this->belongsTo('App\Models\User', 'parent_id');
    }

    /**
     * Get the student related to this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
        return $this->belongsTo('App\Models\User', 'student_id');
    }

    /**
     * Get the admin handling this feedback.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function admin()
    {
        return $this->belongsTo('App\Models\User', 'admin_id');
    }
}
