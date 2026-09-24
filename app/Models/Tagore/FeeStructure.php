<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class FeeStructure extends Model
{
    protected $table = 'tagore_fee_structures';
    protected $guarded = [];
    protected $casts = ['settings_json' => 'array'];
}
