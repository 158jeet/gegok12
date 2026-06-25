<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Conversation;
use App\Models\User;

/**
 * Class Message
 *
 * Model for managing messages within conversations.
 *
 * @property int $id
 * @property int $user_id
 * @property int $conversation_id
 * @property string $body
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property-read \App\Models\Conversation $conversation
 * @property-read \App\Models\User $user
 * @mixin \Eloquent
 */
class Message extends Model
{
	protected $fillable = [
		'user_id',
		'body'
	];

	/**
	 * Check if the message was created by the authenticated user.
	 *
	 * @return bool
	 */
	public function isOwn()
	{
		return $this->user_id === auth()->id();
	}

	/**
	 * Get the conversation this message belongs to.
	 *
	 * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
	 */
    public function conversation()
    {
    	return $this->belongsTo('App\Models\Conversation');
    }

    /**
     * Get the user who sent this message.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
    	return $this->belongsTo('App\Models\User');
    }
}
