<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable =
    [
        'sale_id',
        'product_id',
        'purity',
        'gross_weight',
        'net_weight',
        'fine_weight',
        'qty',
        'metal_rate',
        'labour_amount',
        'other_amount',
        'total_amount'
    ];
}
