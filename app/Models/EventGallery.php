<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class EventGallery
 *
 * Model for managing event gallery images.
 *
 * @property int $id
 * @property int $school_id
 * @property int $event_id
 * @property string $path
 * @property int $created_by
 * @property int $updated_by
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property string $full_path
 * @property-read \App\Models\Events $event
 * @mixin \Eloquent
 */
class EventGallery extends Model
{
	use Common;
    protected $table='event_galleries';

     protected $fillable=['school_id','event_id','path','created_by','updated_by'];

    /**
     * Get the full path for this gallery image.
     *
     * @return string
     */
    public function getFullPathAttribute()
    {
    	return $this->getFilePath($this->path);
    }

    /**
     * Get the event for this gallery image.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function event()
    {
        return $this->belongsTo('App\Models\Events','event_id');
    }


}
