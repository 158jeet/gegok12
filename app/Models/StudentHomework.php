<?php
/**
 * SPDX-License-Identifier: MIT
 * (c) 2025 GegoSoft Technologies and GegoK12 Contributors
 */
namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class StudentHomework
 *
 * Model for managing student homework submissions.
 *
 * @property int $id
 * @property int $homework_id
 * @property int $user_id
 * @property array|null $attachment
 * @property \DateTime $submitted_on
 * @property int|null $checked_by
 * @property \DateTime|null $checked_on
 * @property int $status
 * @property string|null $comments
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property array $attachment_path
 * @property-read \App\Models\Homework $homework
 * @property-read \App\Models\User $student
 * @property-read \App\Models\User|null $teacher
 * @mixin \Eloquent
 */
class StudentHomework extends Model
{
    //
    use SoftDeletes;
    use Common;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'student_homework';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'homework_id' , 'user_id' , 'attachment' , 'submitted_on' , 'checked_by' , 'checked_on' , 'status' , 'comments'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['submitted_on' , 'checked_on' , 'deleted_at'];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts=[ 'attachment' => 'array' ];

    /**
     * Get the homework assignment for this submission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function homework()
    {
        return $this->belongsTo('App\Models\Homework','homework_id');
    }

    /**
     * Get the student who submitted this homework.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function student()
    {
    	return $this->belongsTo('\App\Models\User','user_id');
    }

    /**
     * Get the teacher who checked this homework submission.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function teacher()
    {
        return $this->belongsTo('\App\Models\User','checked_by');
    }

    /**
     * Get the full file paths for all attachments.
     *
     * @return array
     */
    public function getAttachmentPathAttribute()
    {
        if (!$this->attachment || !is_array($this->attachment)) {
            return [];
        }

        $attachment = [];

        foreach ($this->attachment as $index => $file) {

            $attachment[] = [
                'id' => $index,
                'original_path' => $file,
                'path' => $this->getFilePath($file),
            ];
        }

        return $attachment;
    }
}
