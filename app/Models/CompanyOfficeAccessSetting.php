<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyOfficeAccessSetting extends Model
{
    protected $fillable = [
        'company_id',
        'geo_enabled',
        'device_approval_enabled',
        'office_latitude',
        'office_longitude',
        'allowed_radius_meters',
        'emergency_override_enabled',
        'emergency_override_until',
        'emergency_override_reason',
        'emergency_override_by',
    ];

    protected $casts = [
        'geo_enabled' => 'boolean',
        'device_approval_enabled' => 'boolean',
        'office_latitude' => 'decimal:7',
        'office_longitude' => 'decimal:7',
        'allowed_radius_meters' => 'integer',
        'emergency_override_enabled' => 'boolean',
        'emergency_override_until' => 'datetime',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function overrideBy()
    {
        return $this->belongsTo(User::class, 'emergency_override_by');
    }

    public function hasActiveEmergencyOverride(): bool
    {
        if (!$this->emergency_override_enabled) {
            return false;
        }

        return $this->emergency_override_until === null || $this->emergency_override_until->isFuture();
    }
}
