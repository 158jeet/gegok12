<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class LeaveType
 *
 * Model for managing leave types and policies.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $name
 * @property int $max_no_of_days
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property-read \App\Models\School $school
 * @property-read \App\Models\AcademicYear $academicYear
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TeacherLeaveApplication[] $teacherLeaveApplication
 * @mixin \Eloquent
 */
class LeaveType extends Model
{
    use SoftDeletes;

    protected $table = 'leave_types';

    protected $fillable = [
        'school_id' , 'academic_year_id' , 'name' , 'max_no_of_days' , 'status'
    ];

    /**
     * Get the school for this leave type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School','school_id');
    }

    /**
     * Get the academic year for this leave type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('App\Models\AcademicYear','academic_year_id');
    }

    /**
     * Get teacher leave applications for this leave type.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function teacherLeaveApplication()
    {
        return $this->hasMany('\App\Models\TeacherLeaveApplication','leave_type_id','id');
    }
}
