<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SaleReturn extends Model
{
    protected $fillable = [
        'company_id',
        'sale_id',
        'return_voucher_no',
        'return_date',
        'return_total'
    ];

    public function items()
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
