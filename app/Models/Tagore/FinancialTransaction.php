<?php

namespace App\Models\Tagore;

use Illuminate\Database\Eloquent\Model;

class FinancialTransaction extends Model
{
    protected $table = 'tagore_financial_transactions';
    protected $guarded = [];
    protected $casts = ['debit' => 'decimal:2', 'credit' => 'decimal:2', 'balance_after' => 'decimal:2', 'transaction_date' => 'datetime'];
}
