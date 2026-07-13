<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreeCuttingIssueItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'job_worker_id',
        'custom_buch_no',
        'is_custom',
        'receive_tree_wt',
        'issued_by',
        'issued_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'job_worker_id' => 'integer',
        'is_custom' => 'boolean',
        'receive_tree_wt' => 'decimal:3',
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

    public function jobWorker()
    {
        return $this->belongsTo(JobWorker::class);
    }
}
