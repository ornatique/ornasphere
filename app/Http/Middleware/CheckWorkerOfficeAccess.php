<?php

namespace App\Http\Middleware;

use App\Models\CompanyOfficeAccessSetting;
use App\Models\WorkerAccessLog;
use App\Models\WorkerAllowedDevice;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckWorkerOfficeAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company_id || $this->isCompanyAdmin($user)) {
            return $next($request);
        }

        if ($user->mobile_access_allowed === false || (int) $user->mobile_access_allowed === 0) {
            $this->log($request, 'blocked', 'mobile_access_disabled', $this->deviceId($request) ?: null, null, null, null);

            return $this->blocked('Mobile app access is disabled for this worker.', 'mobile_access_disabled');
        }

        $setting = CompanyOfficeAccessSetting::where('company_id', $user->company_id)->first();

        if (!$setting || (!$setting->geo_enabled && !$setting->device_approval_enabled)) {
            return $next($request);
        }

        $deviceId = $this->deviceId($request);
        $latitude = $this->coordinate($request, 'latitude', 'X-Latitude');
        $longitude = $this->coordinate($request, 'longitude', 'X-Longitude');
        $distance = null;

        if ($setting->hasActiveEmergencyOverride()) {
            $this->log($request, 'allowed', 'emergency_override', $deviceId, $latitude, $longitude, null);

            return $next($request);
        }

        if ($setting->device_approval_enabled) {
            if ($deviceId === '') {
                $this->log($request, 'blocked', 'device_id_required', null, $latitude, $longitude, null);

                return $this->blocked('Device approval is required. Please send device_id.', 'device_id_required');
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
                $this->log($request, 'blocked', 'device_not_approved', $deviceId, $latitude, $longitude, null);

                return $this->blocked('This device is not approved for this worker.', 'device_not_approved');
            }
        }

        if ($setting->geo_enabled) {
            if ($latitude === null || $longitude === null) {
                $this->log($request, 'blocked', 'location_required', $deviceId, $latitude, $longitude, null);

                return $this->blocked('Office location access is required. Please send latitude and longitude.', 'location_required');
            }

            if ($setting->office_latitude === null || $setting->office_longitude === null) {
                $this->log($request, 'blocked', 'office_location_not_configured', $deviceId, $latitude, $longitude, null);

                return $this->blocked('Company office location is not configured.', 'office_location_not_configured');
            }

            $distance = $this->distanceMeters(
                (float) $setting->office_latitude,
                (float) $setting->office_longitude,
                $latitude,
                $longitude
            );

            if ($distance > (float) $setting->allowed_radius_meters) {
                $this->log($request, 'blocked', 'outside_office_radius', $deviceId, $latitude, $longitude, $distance);

                return $this->blocked(
                    'Access allowed only inside office radius.',
                    'outside_office_radius',
                    [
                        'distance_meters' => round($distance, 2),
                        'allowed_radius_meters' => (int) $setting->allowed_radius_meters,
                    ]
                );
            }
        }

        $this->log($request, 'allowed', 'inside_office_access', $deviceId, $latitude, $longitude, $distance);

        return $next($request);
    }

    private function isCompanyAdmin($user): bool
    {
        return strtolower((string) $user->role) === 'company_admin' || $user->hasRole('company_admin');
    }

    private function deviceId(Request $request): string
    {
        return trim((string) ($request->input('device_id') ?: $request->header('X-Device-Id')));
    }

    private function coordinate(Request $request, string $inputKey, string $headerKey): ?float
    {
        $value = $request->input($inputKey, $request->header($headerKey));

        return is_numeric($value) ? (float) $value : null;
    }

    private function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;
        $latDelta = deg2rad($lat2 - $lat1);
        $lngDelta = deg2rad($lng2 - $lng1);
        $a = sin($latDelta / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lngDelta / 2) ** 2;

        return $earthRadius * (2 * atan2(sqrt($a), sqrt(1 - $a)));
    }

    private function log(
        Request $request,
        string $status,
        string $reason,
        ?string $deviceId,
        ?float $latitude,
        ?float $longitude,
        ?float $distance
    ): void {
        $user = $request->user();

        WorkerAccessLog::create([
            'company_id' => $user?->company_id,
            'user_id' => $user?->id,
            'device_id' => $deviceId,
            'latitude' => $latitude,
            'longitude' => $longitude,
            'distance_meters' => $distance !== null ? round($distance, 2) : null,
            'status' => $status,
            'reason' => $reason,
            'path' => '/' . ltrim($request->path(), '/'),
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
        ]);
    }

    private function blocked(string $message, string $reason, array $extra = []): Response
    {
        return response()->json(array_merge([
            'success' => false,
            'message' => $message,
            'reason' => $reason,
        ], $extra), 403);
    }
}
