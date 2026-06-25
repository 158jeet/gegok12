<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Models\Users;

use App\Models\User;

/**
 * Class AdminUser
 *
 * Specialized User model for school admin functionality.
 * School/Premium admins (usergroups 3, 4) have access to school-level administrative features.
 *
 * @property-read \App\Models\School $school
 * @property-read \App\Models\Userprofile $userprofile
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Attendance[] $attendanceAdmin
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\TeacherLeaveApplication[] $approvedUser
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\LessonPlanApproval[] $approvedLessonPlan
 * @mixin \Eloquent
 */
class AdminUser extends User
{
    /**
     * Scope to filter school admins only.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeSchoolAdminsOnly($query)
    {
        return $query->whereIn('usergroup_id', [
            self::SCHOOLADMIN_USERGROUP_ID,
            self::SCHOOLSUBADMIN_USERGROUP_ID
        ]);
    }

    /**
     * Scope to filter admins by school.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $school_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForSchool($query, $school_id)
    {
        return $query->where('school_id', $school_id)
            ->whereIn('usergroup_id', [
                self::SCHOOLADMIN_USERGROUP_ID,
                self::SCHOOLSUBADMIN_USERGROUP_ID
            ]);
    }

    /**
     * Check if this admin is primary school admin.
     *
     * @return bool
     */
    public function isPrimaryAdmin(): bool
    {
        return $this->usergroup_id === self::SCHOOLADMIN_USERGROUP_ID;
    }

    /**
     * Check if this admin is sub admin.
     *
     * @return bool
     */
    public function isSubAdmin(): bool
    {
        return $this->usergroup_id === self::SCHOOLSUBADMIN_USERGROUP_ID;
    }

    /**
     * Get all staff members under this school admin.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchoolStaff()
    {
        $staffUsergroups = [
            User::TEACHER_USERGROUP_ID,
            User::LIBRARIAN_USERGROUP_ID,
            User::RECEPTIONIST_USERGROUP_ID,
            User::ACCOUNTANT_USERGROUP_ID,
            User::STOCK_KEEPER_USERGROUP_ID,
            User::NON_TEACHING_USERGROUP_ID
        ];

        return User::where('school_id', $this->school_id)
            ->whereIn('usergroup_id', $staffUsergroups)
            ->get();
    }

    /**
     * Get all students in this school.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchoolStudents()
    {
        return User::where('school_id', $this->school_id)
            ->where('usergroup_id', User::STUDENT_USERGROUP_ID)
            ->get();
    }

    /**
     * Get all parents in this school.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchoolParents()
    {
        return User::where('school_id', $this->school_id)
            ->where('usergroup_id', User::PARENT_USERGROUP_ID)
            ->get();
    }

    /**
     * Get all teachers in this school.
     *
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getSchoolTeachers()
    {
        return User::where('school_id', $this->school_id)
            ->where('usergroup_id', User::TEACHER_USERGROUP_ID)
            ->get();
    }
}
