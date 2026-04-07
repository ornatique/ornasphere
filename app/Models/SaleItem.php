<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected $fillable = [
        'sale_id',
        'itemset_id',
        'product_id',
        'qty',
        'gross_weight',
        'other_weight',
        'net_weight',
        'purity',
        'waste_percent',
        'net_purity',
        'fine_weight',
        'metal_rate',
        'metal_amount',
        'labour_rate',
        'labour_amount',
        'other_amount',
        'total_amount',
        'approval_item_id',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function itemset()
    {
        return $this->belongsTo(ItemSet::class);
    }
}
