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
        'payment_mode',
        'payment_reference',
        'payment_note',
        'use_silver_balance',
        'show_previous_bills',
        'net_total',
        'remarks',
        'employee_id',
        'modified_count',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'modified_count' => 'integer',
        'use_silver_balance' => 'boolean',
        'show_previous_bills' => 'boolean',
    ];


    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function appliedReceivedAmount(): float
    {
        return $this->use_silver_balance
            ? (float) $this->received_amount - (float) $this->paid_amount
            : 0.0;
    }

    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'employee_id');
    }

    public function payments()
    {
        return $this->hasMany(SalePayment::class)->orderBy('paid_on')->orderBy('id');
    }
}
