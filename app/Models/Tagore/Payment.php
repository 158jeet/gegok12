<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    protected $table = 'tagore_payments';
    protected $guarded = [];
    protected $casts = ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
}
