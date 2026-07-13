<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingHeatingItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'in_bhati',
        'checked_by',
        'checked_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'in_bhati' => 'boolean',
        'checked_by' => 'integer',
        'checked_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function voucherItem()
    {
        return $this->belongsTo(VacuumVoucherItem::class, 'vacuum_voucher_item_id');
    }

    public function checkedByUser()
    {
        return $this->belongsTo(User::class, 'checked_by');
    }
}
