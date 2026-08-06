<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreeCuttingOfficeItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'tree_wt',
        'office_cut_wt',
        'remaining_tree_wt',
        'issue_group_key',
        'created_by',
        'updated_by',
        'office_cut_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'tree_wt' => 'decimal:3',
        'office_cut_wt' => 'decimal:3',
        'remaining_tree_wt' => 'decimal:3',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'office_cut_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function voucherItem()
    {
        return $this->belongsTo(VacuumVoucherItem::class, 'vacuum_voucher_item_id');
    }

    public function createdByUser()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedByUser()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
