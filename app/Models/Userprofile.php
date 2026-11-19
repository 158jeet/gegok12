<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Laracasts\Presenter\PresentableTrait;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * Class Userprofile
 *
 * Model for managing user profile information.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property int $usergroup_id
 * @property string $firstname
 * @property string $lastname
 * @property string $alternate_no
 * @property string $gender
 * @property \DateTime $date_of_birth
 * @property string $blood_group
 * @property string $birth_place
 * @property string $native_place
 * @property string $mother_tongue
 * @property string $caste
 * @property string $address
 * @property int $city_id
 * @property int $state_id
 * @property int $country_id
 * @property string $pincode
 * @property string $relation
 * @property string $aadhar_number
 * @property string $registration_number
 * @property string $EMIS_number
 * @property \DateTime $joining_date
 * @property string $notes
 * @property string $avatar
 * @property string $marital_status
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $avatarPath
 * @property-read \App\Models\School $school
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Usergroup $usergroup
 * @property-read \App\Models\Qualification $qualification
 * @property-read \App\Models\Country $country
 * @property-read \App\Models\State $state
 * @property-read \App\Models\City $city
 * @mixin \Eloquent
 */
class Userprofile extends Model
{
    use PresentableTrait;
    protected $presenter = "App\Presenters\UserprofilePresenter";
    use SoftDeletes;
    use Common;
    use HasFactory;

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['date_of_birth','joining_date','deleted_at'];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'userprofiles';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
      'school_id' , 'user_id' , 'usergroup_id' , 'firstname' , 'lastname'  , 'alternate_no' , 'gender' , 'date_of_birth' , 'blood_group' , 'birth_place' , 'native_place' , 'mother_tongue' , 'caste' , 'address' , 'city_id' , 'state_id' , 'country_id' , 'pincode' , 'relation' , 'aadhar_number' , 'registration_number' , 'EMIS_number' , 'joining_date' , 'notes' , 'avatar' , 'marital_status' , 'status'
    ];

    /**
     * Get uppercase first name.
     *
     * @param string $value
     * @return string
     */
    public function getFirstNameAttribute($value)
    {
        return strtoupper($value);
    }

    /**
     * Get uppercase last name.
     *
     * @param string $value
     * @return string
     */
    public function getLastNameAttribute($value)
    {
        return strtoupper((string)$value);
    }

    /**
     * Get the school for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
      return $this->belongsTo('App\Models\School','school_id');
    }

    /**
     * Get the user for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
      return $this->belongsTo('App\Models\User','user_id');
    }

    /**
     * Get the usergroup for this profile.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function usergroup()
    {
      return $this->belongsTo('App\Models\Usergroup','usergroup_id');
    }

    /**
     * Get the qualification for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function qualification()
    {
      return $this->belongsTo('\App\Models\Qualification','qualification_id');
    }

    /**
     * Get the country for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function country()
    {
      return $this->belongsTo('App\Models\Country','country_id');
    }

    /**
     * Get the state for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function state()
    {
      return $this->belongsTo('App\Models\State','state_id');
    }

    /**
     * Get the city for this user.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function city()
    {
      return $this->belongsTo('App\Models\City','city_id');
    }

    /**
     * Scope to filter by school.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $school_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySchool($query,$school_id)
    {
      $query->where('school_id',$school_id);
      return $query;
    }

    /**
     * Scope to filter by user role/group.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $usergroup_id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByRole($query,$usergroup_id)
    {
      $query->where('usergroup_id',$usergroup_id);
      return $query;
    }

    /**
     * Get the avatar file path for this user.
     *
     * @return string|null
     */
    public function getAvatarPathAttribute()
    {
      if($this->avatar!=null)
      {
        return $this->getFilePath($this->avatar);

      }
    }
}
