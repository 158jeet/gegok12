<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class ParentStudent extends Model
{
    protected $table = 'tagore_parent_students';
    protected $guarded = [];
    protected $casts = ['is_primary' => 'boolean', 'is_guardian' => 'boolean'];
}
