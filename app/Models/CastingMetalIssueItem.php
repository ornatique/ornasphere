<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CastingMetalIssueItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'issue_silver_wt',
        'is_if',
        'pure_fine',
        'if_percentage',
        'other_metal',
        'metal_weight',
        'remarks',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'issue_silver_wt' => 'decimal:3',
        'is_if' => 'boolean',
        'pure_fine' => 'decimal:3',
        'if_percentage' => 'decimal:2',
        'other_metal' => 'decimal:3',
        'metal_weight' => 'decimal:3',
        'issued_by' => 'integer',
        'issued_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function voucherItem()
    {
        return $this->belongsTo(VacuumVoucherItem::class, 'vacuum_voucher_item_id');
    }

    public function issuedByUser()
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
