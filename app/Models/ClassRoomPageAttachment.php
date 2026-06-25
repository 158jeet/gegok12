<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Spatie\MediaLibrary\HasMedia\HasMediaTrait;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\MediaLibrary\HasMedia\HasMedia;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\Models\Media;
use App\Traits\Common;

/**
 * Class ClassRoomPageAttachment
 *
 * Model for managing classroom page file attachments.
 *
 * @property int $id
 * @property int $page_id
 * @property string $attachment_file
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $attachment_path
 * @property-read \App\Models\ClassRoomPage $classRoomPage
 * @mixin \Eloquent
 */
class ClassRoomPageAttachment extends Model implements HasMedia
{
    use HasMediaTrait;
    use SoftDeletes;
    use Common;

    protected $table = 'class_room_page_attachments';


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'page_id' , 'attachment_file' , 'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the classroom page for this attachment.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function classRoomPage()
    {
    	return $this->belongsTo('\App\Models\ClassRoomPage','page_id');
    }

    /**
     * Get the full file path for the attachment.
     *
     * @return string
     */
    public function getAttachmentPathAttribute()
    {
        return $this->getFilePath($this->attachment_file);
    }
}
