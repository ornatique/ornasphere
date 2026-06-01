<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerAdvanceLedger extends Model
{
    protected $fillable = [
        'company_id',
        'customer_id',
        'entry_date',
        'entry_type',
        'payment_mode',
        'cash_in',
        'cash_out',
        'metal_type',
        'metal_in',
        'metal_out',
        'rate',
        'reference_type',
        'reference_id',
        'remarks',
        'created_by',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'cash_in' => 'decimal:2',
        'cash_out' => 'decimal:2',
        'metal_in' => 'decimal:3',
        'metal_out' => 'decimal:3',
        'rate' => 'decimal:2',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }
}

