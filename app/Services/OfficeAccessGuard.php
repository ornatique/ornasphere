<?php

namespace App\Services;

use App\Models\CompanyOfficeAccessSetting;
use App\Models\User;
use App\Models\WorkerAccessLog;
use App\Models\WorkerAllowedDevice;
use Illuminate\Http\Request;

class OfficeAccessGuard
{
    public function evaluate(Request $request, User $user): array
    {
        if (!$this->enforced()) {
            return $this->allowed('office_access_bypassed', ['should_log' => false]);
        }

        if (!$user->company_id || $this->isCompanyAdmin($user)) {
            return $this->allowed('company_admin_bypass', ['should_log' => false]);
        }

        $deviceId = $this->deviceId($request);
        $latitude = $this->coordinate($request, 'latitude', 'X-Latitude');
        $longitude = $this->coordinate($request, 'longitude', 'X-Longitude');

        $setting = CompanyOfficeAccessSetting::where('company_id', $user->company_id)->first();

        if ($this->officeAccessDisabled($setting)) {
            return $this->allowed('office_access_disabled', array_merge(
                compact('deviceId', 'latitude', 'longitude'),
                ['should_log' => false]
            ));
        }

        if ($this->isMobileAccessBlocked($user)) {
            return $this->blocked(
                'Mobile app access is disabled for this worker.',
                'mobile_access_disabled',
                compact('deviceId', 'latitude', 'longitude')
            );
        }

        if ($setting->hasActiveEmergencyOverride()) {
            return $this->allowed('emergency_override', compact('deviceId', 'latitude', 'longitude'));
        }

        if ($setting->device_approval_enabled) {
            if ($deviceId === '') {
                return $this->blocked(
                    'Device approval is required. Please send device_id.',
                    'device_id_required',
                    compact('latitude', 'longitude')
                );
            }

            $device = WorkerAllowedDevice::firstOrCreate(
                [
                    'company_id' => $user->company_id,
                    'user_id' => $user->id,
                    'device_id' => $deviceId,
                ],
                [
                    'device_name' => $request->input('device_name') ?: $request->header('X-Device-Name'),
                    'platform' => $request->input('platform') ?: $request->header('X-Platform'),
                    'app_version' => $request->input('app_version') ?: $request->header('X-App-Version'),
                    'status' => WorkerAllowedDevice::STATUS_PENDING,
                ]
            );

            $device->forceFill([
                'last_seen_at' => now(),
                'device_name' => $request->input('device_name') ?: $request->header('X-Device-Name') ?: $device->device_name,
                'platform' => $request->input('platform') ?: $request->header('X-Platform') ?: $device->platform,
                'app_version' => $request->input('app_version') ?: $request->header('X-App-Version') ?: $device->app_version,
            ])->save();

            if (!$device->isApproved()) {
                return $this->blocked(
                    'This device is not approved for this worker.',
                    'device_not_approved',
                    compact('deviceId', 'latitude', 'longitude', 'device')
                );
            }
        }

        if ($setting->geo_enabled) {
            if ($latitude === null || $longitude === null) {
                return $this->blocked(
                    'Office location access is required. Please send latitude and longitude.',
                    'location_required',
                    compact('deviceId', 'latitude', 'longitude')
                );
            }

            if ($setting->office_latitude === null || $setting->office_longitude === null) {
                return $this->blocked(
                    'Company office location is not configured.',
                    'office_location_not_configured',
                    compact('deviceId', 'latitude', 'longitude')
                );
            }

            $distance = $this->distanceMeters(
                (float) $setting->office_latitude,
                (float) $setting->office_longitude,
                $latitude,
                $longitude
            );

            if ($distance > (float) $setting->allowed_radius_meters) {
                return $this->blocked(
                    'Access allowed only inside office radius.',
                    'outside_office_radius',
                    [
                        'deviceId' => $deviceId,
                        'latitude' => $latitude,
                        'longitude' => $longitude,
                        'distance' => $distance,
                        'allowed_radius_meters' => (int) $setting->allowed_radius_meters,
                    ]
                );
            }

            return $this->allowed('inside_office_access', compact('deviceId', 'latitude', 'longitude', 'distance'));
        }

        return $this->allowed('approved_device_access', compact('deviceId', 'latitude', 'longitude'));
    }

    public function log(Request $request, ?User $user, array $decision): void
    {
        WorkerAccessLog::create([
            'company_id' => $user?->company_id,
            'user_id' => $user?->id,
            'device_id' => $decision['device_id'] ?? null,
            'latitude' => $decision['latitude'] ?? null,
            'longitude' => $decision['longitude'] ?? null,
            'distance_meters' => isset($decision['distance_meters']) ? round((float) $decision['distance_meters'], 2) : null,
            'status' => $decision['allowed'] ? 'allowed' : 'blocked',
            'reason' => $decision['reason'],
            'path' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    public function blockedResponse(array $decision)
    {
        return response()->json([
            'success' => false,
            'message' => $decision['message'],
            'reason' => $decision['reason'],
            'distance_meters' => $decision['distance_meters'] ?? null,
            'allowed_radius_meters' => $decision['allowed_radius_meters'] ?? null,
        ], 403);
    }

    public function deviceId(Request $request): string
    {
        return trim((string) ($request->input('device_id') ?: $request->header('X-Device-Id')));
    }

    public function coordinate(Request $request, string $inputKey, string $headerKey): ?float
    {
        $value = $request->input($inputKey, $request->header($headerKey));

        return is_numeric($value) ? (float) $value : null;
    }

    public function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function isCompanyAdmin(User $user): bool
    {
        return strtolower((string) $user->role) === 'company_admin'
            || (method_exists($user, 'hasRole') && $user->hasRole('company_admin'));
    }

    public function isMobileAccessAllowed(User $user): bool
    {
        if (!$this->enforced()) {
            return true;
        }

        $setting = CompanyOfficeAccessSetting::where('company_id', $user->company_id)->first();

        if ($this->officeAccessDisabled($setting)) {
            return true;
        }

        return !$this->isMobileAccessBlocked($user);
    }

    private function officeAccessDisabled(?CompanyOfficeAccessSetting $setting): bool
    {
        return !$setting || (!$setting->geo_enabled && !$setting->device_approval_enabled);
    }

    private function enforced(): bool
    {
        return filter_var(config('office_access.enforced', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function isMobileAccessBlocked(User $user): bool
    {
        $rawValue = $user->getRawOriginal('mobile_access_allowed');

        return $rawValue !== null && (int) $rawValue === 0;
    }

    private function allowed(string $reason, array $context = []): array
    {
        return $this->decision(true, 'Access allowed.', $reason, $context);
    }

    private function blocked(string $message, string $reason, array $context = []): array
    {
        return $this->decision(false, $message, $reason, $context);
    }

    private function decision(bool $allowed, string $message, string $reason, array $context = []): array
    {
        return [
            'allowed' => $allowed,
            'message' => $message,
            'reason' => $reason,
            'device_id' => $context['deviceId'] ?? null,
            'latitude' => $context['latitude'] ?? null,
            'longitude' => $context['longitude'] ?? null,
            'distance_meters' => isset($context['distance']) ? round((float) $context['distance'], 2) : null,
            'allowed_radius_meters' => $context['allowed_radius_meters'] ?? null,
            'device' => $context['device'] ?? null,
            'should_log' => $context['should_log'] ?? true,
        ];
    }
}
