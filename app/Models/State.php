<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class State
 *
 * Model for managing states/provinces in countries.
 *
 * @property int $id
 * @property int $country_id
 * @property string $name
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read \App\Models\Country $country
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\School[] $school
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Userprofile[] $userprofile
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\City[] $city
 * @mixin \Eloquent
 */
class State extends Model
{
    use HasFactory;

    //

    /**
     * The table associated with the model.
     *
     * @var string
     */
	protected $table = 'states';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'country_id' , 'name' , 'status'
    ];

    /**
     * Get the country for this state.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
        return $this->belongsTo('App\Models\Country','country_id');
    }

    /**
     * Get schools in this state.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function school()
    {
        return $this->hasMany('App\Models\School','state_id','id');
    }

    /**
     * Get user profiles from this state.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function userprofile()
    {
        return $this->hasMany('App\Models\Userprofile','state_id','id');
    }

    /**
     * Get cities in this state.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function city()
    {
        return $this->hasMany('App\Models\City','state_id','id');
    }
}
