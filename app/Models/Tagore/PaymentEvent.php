<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class PaymentEvent extends Model
{
    protected $table = 'tagore_payment_events';
    protected $guarded = [];
    protected $casts = ['payload_json' => 'array', 'received_at' => 'datetime', 'processed_at' => 'datetime'];
}
