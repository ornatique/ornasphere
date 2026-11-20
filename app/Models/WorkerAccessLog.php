<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkerAccessLog extends Model
{
    protected $fillable = [
        'company_id',
        'user_id',
        'device_id',
        'latitude',
        'longitude',
        'distance_meters',
        'allowed_radius_meters',
        'status',
        'reason',
        'path',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'distance_meters' => 'decimal:2',
        'allowed_radius_meters' => 'integer',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
