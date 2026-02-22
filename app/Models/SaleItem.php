<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'itemset_id',
        'gross_weight',
        'net_weight',
        'purity',
        'fine_weight',
        'total_amount'
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function itemset()
    {
        return $this->belongsTo(Itemset::class);
    }
}