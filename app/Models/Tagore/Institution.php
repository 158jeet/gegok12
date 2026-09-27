<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Institution extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_institutions';
    protected $guarded = [];
    protected $casts = ['settings_json' => 'array'];

    public function group()
    {
        return $this->belongsTo(Group::class, 'tagore_group_id');
    }

    public function school()
    {
        return $this->belongsTo(\App\Models\School::class, 'school_id');
    }
}
