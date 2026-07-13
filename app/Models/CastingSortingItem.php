<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingSortingItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'item_id',
        'weight',
        'quantity',
        'sorted_by',
        'sorted_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'item_id' => 'integer',
        'weight' => 'decimal:3',
        'quantity' => 'integer',
        'sorted_by' => 'integer',
        'sorted_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function sortedByUser()
    {
        return $this->belongsTo(User::class, 'sorted_by');
    }
}
