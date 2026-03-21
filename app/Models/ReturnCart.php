<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReturnCart extends Model
{
    protected $fillable = [
        'user_id',
        'company_id',
        'sale_item_id'
    ];

    public function saleItem()
    {
        return $this->belongsTo(SaleItem::class);
    }
}
