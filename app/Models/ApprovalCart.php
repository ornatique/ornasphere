<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ApprovalCart extends Model
{
    use HasFactory;

    protected $table = 'approval_carts';

    protected $fillable = [
        'user_id',
        'company_id',
        'customer_id',
        'itemset_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function itemset()
    {
        return $this->belongsTo(ItemSet::class);
    }
}

