<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class ClassRoomPage
 *
 * Model for managing classroom pages and content.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property string $page_name
 * @property int $category_id
 * @property string $description
 * @property string $cover_image
 * @property int $created_by
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $cover_image_path
 * @property-read \App\Models\School $school
 * @property-read \App\Models\AcademicYear $academicYear
 * @property-read \App\Models\User $user
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ClassRoomPageDetail[] $classRoomPageDetail
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\ClassRoomPageAttachment[] $classRoomPageAttachment
 * @property-read \App\Models\ClassRoomPageCategory $classRoomPageCategory
 * @mixin \Eloquent
 */
class ClassRoomPage extends Model
{
    use SoftDeletes;
    use Common;

    protected $table = 'class_room_pages';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'academic_year_id' , 'page_name' , 'category_id' , 'description' , 'cover_image' , 'created_by' , 'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the school for this classroom page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
    	return $this->belongsTo('\App\Models\School','school_id');
    }

    /**
     * Get the academic year for this classroom page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('\App\Models\AcademicYear','academic_year_id');
    }

    /**
     * Get the user who created this page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
    	return $this->belongsTo('\App\Models\User','created_by');
    }

    /**
     * Get the details for this classroom page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classRoomPageDetail()
    {
    	return $this->hasMany('\App\Models\ClassRoomPageDetail','page_id','id');
    }

    /**
     * Get the attachments for this classroom page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function classRoomPageAttachment()
    {
    	return $this->hasMany('\App\Models\ClassRoomPageAttachment','page_id','id');
    }

    /**
     * Get the category for this classroom page.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classRoomPageCategory()
    {
        return $this->belongsTo('\App\Models\ClassRoomPageCategory','category_id');
    }

    /**
     * Get the full file path for the cover image.
     *
     * @return string
     */
    public function getCoverImagePathAttribute()
    {
        return $this->getFilePath($this->cover_image);
    }
}
