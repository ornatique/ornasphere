<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'itemset_id',
        'product_id',
        'return_amount'
    ];

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
    public function approvalItem()
    {
        return $this->belongsTo(ApprovalItem::class, 'approval_item_id');
    }

    public function itemSet()
    {
        return $this->belongsTo(ItemSet::class, 'itemset_id');
    }
    
}
