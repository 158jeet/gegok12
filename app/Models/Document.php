<?php
// SPDX-License-Identifier: MIT
// (c) 2025 GegoSoft Technologies and GegoK12 Contributors

namespace App\Models;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Common;

/**
 * Class Document
 *
 * Model for managing documents and files.
 *
 * @property int $id
 * @property int $school_id
 * @property int $user_id
 * @property string $version
 * @property string $type
 * @property string $name
 * @property string $file_path
 * @property int $status
 * @property \DateTime $created_at
 * @property \DateTime $updated_at
 * @property \DateTime $deleted_at
 * @property string $path
 * @property-read \App\Models\School $school
 * @property-read \App\Models\User $user
 * @mixin \Eloquent
 */
class Document extends Model
{
    use SoftDeletes;
    use Common;

    protected $table = 'documents';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'school_id' , 'user_id' , 'version' , 'type' , 'name' , 'file_path', 'status'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = ['deleted_at'];

    /**
     * Get the school for this document.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function school()
    {
        return $this->belongsTo('App\Models\School','school_id');
    }

    /**
     * Get the user who uploaded this document.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function user()
    {
        return $this->belongsTo('App\Models\User','user_id');
    }

    /**
     * Get the full file path for this document.
     *
     * @return string
     */
    public function getPathAttribute()
    {
      return $this->getFilePath($this->file_path);
    }
}
