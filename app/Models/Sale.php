<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Customer;
use App\Models\User;

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
        'remarks',
        'employee_id',
        'modified_count',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'modified_count' => 'integer',
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }
}
