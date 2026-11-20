<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class PostComment
 *
 * Model for managing comments on posts.
 *
 * @property int $id
 * @property int $user_id
 * @property int $entity_id
 * @property string $entity_name
 * @property string $comments
 * @property string $attachment_file
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $attachment_path
 * @property string $postCommentDetails
 * @property int $commentLikeCount
 * @property int $commentUnlikeCount
 * @property-read \App\Models\User $user
 * @property-read \App\Models\Post $post
 * @property-read \App\Models\PostComment $postComment
 * @property-read \Illuminate\Database\Eloquent\Collection|\App\Models\PostCommentDetail[] $postCommentDetail
 * @mixin \Eloquent
 */
class PostComment extends Model
{
    //
    use SoftDeletes;
    use Common;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'post_comments';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'user_id' , 'entity_id' , 'entity_name' , 'comments' , 'attachment_file' , 'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the user who made this comment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
    	return $this->belongsTo('\App\Models\User','user_id');
    }

    /**
     * Get the post this comment belongs to.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function post()
    {
        return $this->belongsTo('\App\Models\Post','entity_id');
    }

    /**
     * Get the parent comment if this is a reply.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function postComment()
    {
        return $this->belongsTo('\App\Models\PostComment','entity_id');
    }

    /**
     * Get the details of this comment (likes/unlikes).
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function postCommentDetail()
    {
        return $this->hasMany('\App\Models\PostCommentDetail','post_comment_id','id');
    }

    /**
     * Get the attachment file path for this comment.
     *
     * @return string
     */
    public function getAttachmentPathAttribute()
    {
        return $this->getFilePath($this->attachment_file);
    }

    /**
     * Get formatted comment details with user information.
     *
     * @return array
     */
    public function getPostCommentDetailsAttribute()
    {
        $i = 0;
        $array = [];
        foreach ($this->postCommentDetail as $postCommentDetail)
        {
            $array[$i]['detail_id']         = $postCommentDetail->id;
            $array[$i]['user_id']           = $postCommentDetail->user_id;
            $array[$i]['user_name']         = $postCommentDetail->user->name;
            $array[$i]['user_fullname']     = ucwords($postCommentDetail->user->FullName);
            $array[$i]['user_avatar']       = $postCommentDetail->user->userprofile->AvatarPath;
            $i++;
        }

        return $array;
    }

    /**
     * Get count of likes for this comment.
     *
     * @return int
     */
    public function getCommentLikeCountAttribute()
    {
        if($this->postCommentDetail != null)
        {
            return $this->postCommentDetail->where('like',1)->count();
        }
        return 0;
    }

    /**
     * Get count of unlikes for this comment.
     *
     * @return int
     */
    public function getCommentUnlikeCountAttribute()
    {
        if($this->postCommentDetail != null)
        {
            return $this->postCommentDetail->where('unlike',1)->count();
        }
        return 0;
    }
}
