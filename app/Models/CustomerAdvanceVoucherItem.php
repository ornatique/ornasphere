<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAdvanceVoucherItem extends Model
{
    protected $fillable = [
        'voucher_id',
        'itemset_id',
        'product_id',
        'label_code',
        'huid',
        'item_name',
        'metal_type',
        'gross_weight',
        'other_weight',
        'net_weight',
        'purity',
        'waste_percent',
        'net_purity',
        'fine_weight',
        'metal_rate',
        'apply_metal',
        'metal_amount',
        'labour_rate',
        'apply_labour',
        'labour_amount',
        'other_amount',
        'total_amount',
        'remarks',
    ];

    protected $casts = [
        'apply_metal' => 'boolean',
        'apply_labour' => 'boolean',
    ];

    public function voucher()
    {
        return $this->belongsTo(CustomerAdvanceVoucher::class, 'voucher_id');
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
