<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobworkIssue extends Model
{
    protected $fillable = [
        'company_id',
        'voucher_no',
        'jobwork_date',
        'job_worker_id',
        'production_step_id',
        'remarks',
        'created_by',
        'updated_by',
        'modified_count',
    ];

    protected $casts = [
        'jobwork_date' => 'date',
        'job_worker_id' => 'integer',
        'production_step_id' => 'integer',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jobWorker()
    {
        return $this->belongsTo(JobWorker::class);
    }

    public function productionStep()
    {
        return $this->belongsTo(ProductionStep::class);
    }

    public function items()
    {
        return $this->hasMany(JobworkIssueItem::class);
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

