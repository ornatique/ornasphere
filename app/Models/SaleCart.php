<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleCart extends Model
{
    use HasFactory;

    protected $table = 'sale_carts';

    protected $fillable = [
        'user_id',
        'company_id',
        'itemset_id',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    // Cart belongs to User
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Cart belongs to Company
    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    // Cart belongs to Itemset
    public function itemset()
    {
        return $this->belongsTo(Itemset::class);
    }
}