<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyPlanRenewal extends Model
{
    protected $fillable = [
        'company_id',
        'old_expires_at',
        'new_expires_at',
        'renewed_by',
        'remarks',
    ];

    protected $casts = [
        'old_expires_at' => 'datetime',
        'new_expires_at' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function renewedBy()
    {
        return $this->belongsTo(SuperAdmin::class, 'renewed_by');
    }
}
