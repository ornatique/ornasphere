<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected static function booted(): void
    {
        static::updated(function (Company $company) {
            if ($company->wasChanged('status') && !(bool) $company->status) {
                \App\Services\CompanyDeactivation::resetUsers($company);
            } elseif ($company->wasChanged('status') && (bool) $company->status) {
                \App\Services\CompanyDeactivation::activateUsers($company);
            }
        });
    }

    protected $fillable = [
        'name',
        'slug',
        'email',
        'company_logo',
        'max_users',
        'plan',
        'plan_started_at',
        'plan_expires_at',
        'plan_renewed_at',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'status'
    ];

    protected $casts = [
        'plan_started_at' => 'datetime',
        'plan_expires_at' => 'datetime',
        'plan_renewed_at' => 'datetime',
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function branches()
    {
        return $this->hasMany(self::class, 'parent_company_id');
    }

    public function getCompanyLogoUrlAttribute(): ?string
    {
        $defaultLogo = asset('celestial/assets/images/ornasphere-auth-logo.png') . '?v=' . @filemtime(public_path('celestial/assets/images/ornasphere-auth-logo.png'));
        $rawPath = trim((string) ($this->attributes['company_logo'] ?? ''));
        if ($rawPath === '') {
            return $defaultLogo;
        }

        if (preg_match('/^https?:\/\//i', $rawPath)) {
            return $rawPath;
        }

        $path = ltrim($rawPath, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (file_exists(public_path($path))) {
            return asset($path);
        }

        if (str_starts_with($path, 'storage/')) {
            return asset($path);
        }

        if (file_exists(storage_path('app/public/' . $path))) {
            return asset('storage/' . $path);
        }

        if (str_starts_with($path, 'uploads/') || str_starts_with($path, 'celestial/')) {
            return asset($path);
        }

        return $defaultLogo;
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function officeAccessSetting()
    {
        return $this->hasOne(CompanyOfficeAccessSetting::class);
    }

    public function workerAllowedDevices()
    {
        return $this->hasMany(WorkerAllowedDevice::class);
    }

    public function planRenewals()
    {
        return $this->hasMany(CompanyPlanRenewal::class);
    }
}
