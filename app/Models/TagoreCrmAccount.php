<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class TagoreCrmAccount extends Model
{
    use SoftDeletes;

    protected $table = 'tagore_crm_accounts';

    protected $fillable = [
        'institution_id', 'parent_account_id', 'account_type', 'name', 'website',
        'industry', 'phone', 'email', 'address_line', 'city', 'state', 'country',
        'owner_user_id', 'created_by', 'status', 'notes',
    ];

    public function parentAccount()
    {
        return $this->belongsTo(self::class, 'parent_account_id');
    }

    public function childAccounts()
    {
        return $this->hasMany(self::class, 'parent_account_id');
    }

    public function contacts()
    {
        return $this->hasMany(TagoreCrmContact::class, 'account_id');
    }

    public function opportunities()
    {
        return $this->hasMany(TagoreCrmOpportunity::class, 'account_id');
    }

    public function tasks()
    {
        return $this->hasMany(TagoreCrmTask::class, 'account_id');
    }

    public function cases()
    {
        return $this->hasMany(TagoreCrmCase::class, 'account_id');
    }
}
