<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobworkReceive extends Model
{
    protected $fillable = [
        'company_id',
        'jobwork_issue_id',
        'receive_date',
        'remarks',
        'created_by',
        'updated_by',
        'modified_count',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'jobwork_issue_id' => 'integer',
        'receive_date' => 'date',
        'created_by' => 'integer',
        'updated_by' => 'integer',
        'modified_count' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function jobworkIssue()
    {
        return $this->belongsTo(JobworkIssue::class);
    }

    public function items()
    {
        return $this->hasMany(JobworkReceiveItem::class);
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
