<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    protected static function booted(): void
    {
        static::saving(fn ($line) => \App\Services\FixedSalePrice::saveSnapshot($line));
    }

    protected $fillable = [
        'sale_price',
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
        'other_charge_details',
        'total_amount',
        'remarks',
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

    public function product()
    {
        return $this->belongsTo(Item::class, 'product_id');
    }
}
