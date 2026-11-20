<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class Events
 *
 * Model for managing school events and activities.
 *
 * @property int $id
 * @property int $school_id
 * @property int $academic_year_id
 * @property int $standard_id
 * @property string $select_type
 * @property string $title
 * @property string $description
 * @property string $repeats
 * @property string $freq
 * @property string $freq_term
 * @property string $location
 * @property string $category
 * @property string $organised_by
 * @property string $image
 * @property \DateTime $start_date
 * @property \DateTime $end_date
 * @property int $allDay
 * @property string $url
 * @property int $created_by
 * @property int $updated_by
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $image_path
 * @property-read \App\Models\School $school
 * @property-read \App\Models\AcademicYear $academicYear
 * @property-read \App\Models\StandardLink $standardlink
 * @property-read \App\Models\User $createdBy
 * @property-read \App\Models\User $updatedBy
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Notes[] $notes
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Reminder[] $eventreminder
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\EventGallery[] $eventgallery
 * @mixin \Eloquent
 */
class Events extends Model
{
  use SoftDeletes;
  use Common;

    protected $table    = 'events';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'academic_year_id' , 'standard_id' , 'select_type' , 'title' , 'description' , 'repeats' , 'freq' , 'freq_term' , 'location' , 'category' , 'organised_by' , 'image' , 'start_date' , 'end_date' , 'allDay' , 'url' , 'created_by' , 'updated_by','status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    //protected $dates = ['start_date' ,  'end_date' , 'deleted_at'];

    protected $casts = [
        'start_date' => 'datetime',
        'end_date' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the school for this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School','school_id');
    }

    /**
     * Get the academic year for this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function academicYear()
    {
        return $this->belongsTo('\App\Models\AcademicYear','academic_year_id');
    }

    /**
     * Get the standard link for this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function standardlink()
    {
        return $this->belongsTo('App\Models\StandardLink','standard_id');
    }

    /**
     * Get the user who created this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function createdBy()
    {
      return $this->belongsTo('App\Models\User','created_by');
    }

    /**
     * Get the user who last updated this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function updatedBy()
    {
      return $this->belongsTo('App\Models\User','updated_by');
    }

    /**
     * Get notes related to this event.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function notes()
    {
        return $this->hasMany('App\Models\Notes','entity_id','id');
    }

    /**
     * Scope to filter events by church.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $church_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByChurch($query,$church_id)
    {
        $query->where('church_id',$church_id);
        return $query;
    }

    /**
     * Get event reminders.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventreminder()
    {
        return $this->hasMany('App\Models\Reminder', 'entity_id','id')->where('entity_name','=','App\\Models\\Events');
    }

    /**
     * Get event gallery images.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function eventgallery()
    {
        return $this->hasMany('App\Models\EventGallery','event_id','id');
    }

    /**
     * Get the image path for this event.
     *
     * @return string|null
     */
    public function getImagePathAttribute()
    {
        if($this->image==null)
        {
            return $this->eventImagePath($this->category,$this->image);
        }
    }

    /**
     * Get count of photos in this event gallery.
     *
     * @param int $id
     * @param int $school_id
     * @return int
     */
    public function getphotocount($id,$school_id)
    {
       $count=EventGallery::where('school_id',$school_id)->where('event_id',$id)->count();

       return $count;
    }

    /**
     * Scope to filter events by date range.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \DateTime $start_date
     * @param \DateTime $end_date
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByDate($query,$start_date,$end_date)
    {
       $query->where(function ($q) use ($start_date, $end_date) {
            $q->where('start_date', '>=', $start_date)
               ->where('start_date', '<', $end_date);

        })->orWhere(function ($q) use ($start_date, $end_date) {
            $q->where('start_date', '<=', $start_date)
               ->where('end_date', '>', $end_date);

        })->orWhere(function ($q) use ($start_date, $end_date) {
            $q->where('end_date', '>', $start_date)
               ->where('end_date', '<=', $end_date);

        })->orWhere(function ($q) use ($start_date, $end_date) {
            $q->where('start_date', '>=', $start_date)
               ->where('end_date', '<=', $end_date);

        });

        return $query;
    }
}
