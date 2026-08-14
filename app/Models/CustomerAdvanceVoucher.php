<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAdvanceVoucher extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'ledger_id',
        'voucher_no',
        'voucher_date',
        'entry_type',
        'payment_mode',
        'amount',
        'cash_in',
        'cash_out',
        'metal_type',
        'metal_in',
        'metal_out',
        'rate',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'voucher_date' => 'date',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function ledger()
    {
        return $this->belongsTo(CustomerAdvanceLedger::class, 'ledger_id');
    }

    public function items()
    {
        return $this->hasMany(CustomerAdvanceVoucherItem::class, 'voucher_id');
    }
}
