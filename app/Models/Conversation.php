<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Message;

/**
 * Class Conversation
 *
 * Model for managing conversations between users.
 *
 * @property int $id
 * @property string $uuid
 * @property \DateTime $last_message_at
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $users
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\User[] $others
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\Message[] $messages
 * @mixin \Eloquent
 */
class Conversation extends Model
{

	protected $fillable = [
		'last_message_at',
         'uuid'

	];

	protected $dates = [
		'last_message_at'


	];

    /**
     * Get the route key name for the model.
     *
     * @return string
     */
	public function getRouteKeyName()
	{
		return 'uuid';
	}

    /**
     * Get users participating in this conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function users()
    {
        return $this->belongsToMany('App\Models\User')->withPivot('read_at')
            ->withTimestamps()
        	->oldest();


    }

    /**
     * Get other users in this conversation (excluding authenticated user).
     *
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function others()
    {
        return $this->users()->where('user_id','!=',auth()->id());
    }

    /**
     * Get messages in this conversation.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function messages()
    {
    	return $this->hasMany('App\Models\Message')->latest();
    }
}
