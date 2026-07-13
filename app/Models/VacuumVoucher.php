<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VacuumVoucher extends Model
{
    protected $fillable = [
        'company_id',
        'voucher_no',
        'voucher_date',
        'vacuum_process_id',
        'job_worker_id',
        'formula_value',
        'gross_wt_total',
        'buch_wt_total',
        'net_wt_total',
        'silver_wt_total',
        'remarks',
        'created_by',
        'updated_by',
        'modified_count',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'voucher_date' => 'date',
        'vacuum_process_id' => 'integer',
        'job_worker_id' => 'integer',
        'formula_value' => 'decimal:3',
        'gross_wt_total' => 'decimal:3',
        'buch_wt_total' => 'decimal:3',
        'net_wt_total' => 'decimal:3',
        'silver_wt_total' => 'decimal:3',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
    ];

    public function items()
    {
        return $this->hasMany(VacuumVoucherItem::class);
    }

    public function heatingItems()
    {
        return $this->hasMany(CastingHeatingItem::class);
    }

    public function process()
    {
        return $this->belongsTo(VacuumProcess::class, 'vacuum_process_id');
    }

    public function jobWorker()
    {
        return $this->belongsTo(JobWorker::class);
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
