<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalePayment extends Model
{
    protected $fillable = [
        'company_id',
        'sale_id',
        'amount',
        'paid_on',
        'payment_mode',
        'payment_reference',
        'payment_note',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_on' => 'date',
    ];

    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }
}
