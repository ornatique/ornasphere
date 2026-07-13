<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TreeCuttingReceiveItem extends Model
{
    protected $fillable = [
        'company_id',
        'vacuum_voucher_id',
        'vacuum_voucher_item_id',
        'tree_cutting_issue_item_id',
        'job_worker_id',
        'custom_buch_no',
        'is_custom',
        'receive_pc_wt',
        'receive_tree_bhuko',
        'loss',
        'received_by',
        'received_at',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'vacuum_voucher_id' => 'integer',
        'vacuum_voucher_item_id' => 'integer',
        'tree_cutting_issue_item_id' => 'integer',
        'job_worker_id' => 'integer',
        'is_custom' => 'boolean',
        'receive_pc_wt' => 'decimal:3',
        'receive_tree_bhuko' => 'decimal:3',
        'loss' => 'decimal:3',
        'received_by' => 'integer',
        'received_at' => 'datetime',
    ];

    public function voucher()
    {
        return $this->belongsTo(VacuumVoucher::class, 'vacuum_voucher_id');
    }

    public function voucherItem()
    {
        return $this->belongsTo(VacuumVoucherItem::class, 'vacuum_voucher_item_id');
    }

    public function receivedByUser()
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function issueItem()
    {
        return $this->belongsTo(TreeCuttingIssueItem::class, 'tree_cutting_issue_item_id');
    }

    public function jobWorker()
    {
        return $this->belongsTo(JobWorker::class);
    }
}
