<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerAllowedDevice extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'company_id',
        'user_id',
        'device_id',
        'device_name',
        'platform',
        'app_version',
        'status',
        'approved_at',
        'approved_by',
        'last_seen_at',
        'last_latitude',
        'last_longitude',
    ];

    protected $casts = [
        'approved_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'last_latitude' => 'decimal:7',
        'last_longitude' => 'decimal:7',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }
}
