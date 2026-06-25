<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

/**
 * Class Section
 *
 * Model for managing school sections/divisions.
 *
 * @property int $id
 * @property int $school_id
 * @property string $name
 * @property string $value
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property-read \App\Models\School $school
 * @property-read \App\Models\Promotion $currentPromotion
 * @property-read \App\Models\Promotion $nextPromotion
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Subject[] $subject
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\StandardLink[] $standardLink
 * @mixin \Eloquent
 */
class Section extends Model
{
    //
    use SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'sections';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
       'school_id' , 'name' , 'value' , 'status'
    ];

    /**
     * Get the school for this section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
    	return $this->belongsTo('\App\Models\School','school_id');
    }

    /**
     * Get the current promotion for this section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function currentPromotion()
    {
        return $this->belongsTo('App\Models\Promotion','section_id','id');
    }

    /**
     * Get the next promotion for this section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function nextPromotion()
    {
        return $this->belongsTo('App\Models\Promotion','section_id','id');
    }

    /**
     * Get subjects in this section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subject()
    {
        return $this->hasMany('\App\Models\Subject','section_id','id');
    }

    /**
     * Get standard links for this section.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function standardLink()
    {
        return $this->hasMany('App\Models\StandardLink','section_id','id');
    }
}
