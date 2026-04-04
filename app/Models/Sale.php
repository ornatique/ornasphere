<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;

class Sale extends Model
{
    protected $fillable =
    [
        'company_id',
        'customer_id',
        'voucher_series',
        'voucher_no',
        'sale_date',
        'invoice_type',
        'total_amount',
        'received_amount',
        'paid_amount',
        'net_total',
        'remarks'
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }
}
