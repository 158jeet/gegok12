<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class FeeObligation extends Model
{
    protected $table = 'tagore_fee_obligations';
    protected $guarded = [];
    protected $casts = [
        'due_date' => 'date',
        'gross_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'concession_amount' => 'decimal:2',
        'net_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'outstanding_amount' => 'decimal:2',
    ];
}
