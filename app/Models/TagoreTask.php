<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TagoreTask extends Model
{
    protected $table = 'tagore_tasks';

    protected $fillable = [
        'institution_id','department_id','created_by','assigned_to','title','description',
        'priority','status','progress','due_at','completed_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];
}
