<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Company extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'email',
        'company_logo',
        'max_users',
        'plan',
        'address_1',
        'address_2',
        'city',
        'state',
        'postcode',
        'country',
        'status'
    ];

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function getCompanyLogoUrlAttribute(): ?string
    {
        $rawPath = trim((string) ($this->attributes['company_logo'] ?? ''));
        if ($rawPath === '') {
            return null;
        }

        $path = ltrim($rawPath, '/');
        if (str_starts_with($path, 'public/')) {
            $path = substr($path, 7);
        }

        if (file_exists(public_path($path))) {
            return asset('public/' . $path);
        }

        if (file_exists(storage_path('app/public/' . $path))) {
            return asset('storage/' . $path);
        }

        return null;
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
}
