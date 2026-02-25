<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturnItem extends Model
{
    protected $fillable = [
        'sale_return_id',
        'sale_item_id',
        'return_amount'
    ];

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
