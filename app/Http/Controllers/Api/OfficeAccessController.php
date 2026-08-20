<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanyOfficeAccessSetting;
use App\Models\WorkerAllowedDevice;
use App\Services\OfficeAccessGuard;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OfficeAccessController extends Controller
{
    public function settings(Request $request)
    {
        $this->authorizeCompanyAdmin($request);

        return response()->json([
            'success' => true,
            'data' => $this->setting($request),
        ]);
    }

    public function updateSettings(Request $request)
    {
        $this->authorizeCompanyAdmin($request);

        $validated = $request->validate([
            'geo_enabled' => 'nullable|boolean',
            'device_approval_enabled' => 'nullable|boolean',
            'office_latitude' => 'nullable|numeric|between:-90,90',
            'office_longitude' => 'nullable|numeric|between:-180,180',
            'allowed_radius_meters' => 'nullable|integer|min:10|max:10000',
        ]);

        $setting = $this->setting($request);
        $setting->fill([
            'geo_enabled' => $request->boolean('geo_enabled', $setting->geo_enabled),
            'device_approval_enabled' => $request->boolean('device_approval_enabled', $setting->device_approval_enabled),
            'office_latitude' => $validated['office_latitude'] ?? $setting->office_latitude,
            'office_longitude' => $validated['office_longitude'] ?? $setting->office_longitude,
            'allowed_radius_meters' => $validated['allowed_radius_meters'] ?? $setting->allowed_radius_meters,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => 'Office access settings updated successfully.',
            'data' => $setting->fresh(),
        ]);
    }

    public function devices(Request $request)
    {
        $this->authorizeCompanyAdmin($request);

        $devices = WorkerAllowedDevice::with(['user:id,name,email,role', 'approvedBy:id,name'])
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->status))
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $devices,
        ]);
    }

    public function updateDeviceStatus(Request $request, WorkerAllowedDevice $device)
    {
        $this->authorizeCompanyAdmin($request);

        if ((int) $device->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                WorkerAllowedDevice::STATUS_APPROVED,
                WorkerAllowedDevice::STATUS_REJECTED,
                WorkerAllowedDevice::STATUS_INACTIVE,
                WorkerAllowedDevice::STATUS_PENDING,
            ])],
        ]);

        $payload = ['status' => $validated['status']];
        if ($validated['status'] === WorkerAllowedDevice::STATUS_APPROVED) {
            $payload['approved_at'] = now();
            $payload['approved_by'] = $request->user()->id;
        }

        $device->forceFill($payload)->save();

        return response()->json([
            'success' => true,
            'message' => 'Device status updated successfully.',
            'data' => $device->fresh(['user:id,name,email,role', 'approvedBy:id,name']),
        ]);
    }

    public function approveDevice(Request $request, WorkerAllowedDevice $device)
    {
        return $this->setDeviceStatus($request, $device, WorkerAllowedDevice::STATUS_APPROVED);
    }

    public function rejectDevice(Request $request, WorkerAllowedDevice $device)
    {
        return $this->setDeviceStatus($request, $device, WorkerAllowedDevice::STATUS_REJECTED);
    }

    public function inactiveDevice(Request $request, WorkerAllowedDevice $device)
    {
        return $this->setDeviceStatus($request, $device, WorkerAllowedDevice::STATUS_INACTIVE);
    }

    public function approveDeviceByRequest(Request $request)
    {
        return $this->setDeviceStatusByRequest($request, WorkerAllowedDevice::STATUS_APPROVED);
    }

    public function rejectDeviceByRequest(Request $request)
    {
        return $this->setDeviceStatusByRequest($request, WorkerAllowedDevice::STATUS_REJECTED);
    }

    public function inactiveDeviceByRequest(Request $request)
    {
        return $this->setDeviceStatusByRequest($request, WorkerAllowedDevice::STATUS_INACTIVE);
    }

    public function setEmergencyOverride(Request $request)
    {
        $this->authorizeCompanyAdmin($request);

        $validated = $request->validate([
            'enabled' => 'required|boolean',
            'until' => 'nullable|date',
            'reason' => 'nullable|string|max:1000',
        ]);

        $setting = $this->setting($request);
        $setting->forceFill([
            'emergency_override_enabled' => (bool) $validated['enabled'],
            'emergency_override_until' => $validated['enabled'] ? ($validated['until'] ?? null) : null,
            'emergency_override_reason' => $validated['enabled'] ? ($validated['reason'] ?? null) : null,
            'emergency_override_by' => $validated['enabled'] ? $request->user()->id : null,
        ])->save();

        return response()->json([
            'success' => true,
            'message' => $setting->emergency_override_enabled
                ? 'Emergency override enabled.'
                : 'Emergency override disabled.',
            'data' => $setting->fresh(),
        ]);
    }

    public function registerDevice(Request $request)
    {
        $validated = $request->validate([
            'device_id' => 'required|string|max:191',
            'device_name' => 'nullable|string|max:255',
            'platform' => 'nullable|string|max:100',
            'app_version' => 'nullable|string|max:100',
        ]);

        $user = $request->user();
        $device = WorkerAllowedDevice::firstOrCreate(
            [
                'company_id' => $user->company_id,
                'user_id' => $user->id,
                'device_id' => $validated['device_id'],
            ],
            ['status' => WorkerAllowedDevice::STATUS_PENDING]
        );

        $device->forceFill([
            'device_name' => $validated['device_name'] ?? $device->device_name,
            'platform' => $validated['platform'] ?? $device->platform,
            'app_version' => $validated['app_version'] ?? $device->app_version,
            'last_seen_at' => now(),
        ])->save();

        return response()->json([
            'success' => true,
            'message' => $device->isApproved()
                ? 'Device is approved.'
                : 'Device registered and waiting for company admin approval.',
            'data' => $device->fresh(),
        ]);
    }

    public function status(Request $request, OfficeAccessGuard $officeAccessGuard)
    {
        $user = $request->user();
        $setting = CompanyOfficeAccessSetting::where('company_id', $user->company_id)->first();
        $decision = $officeAccessGuard->evaluate($request, $user);
        $deviceId = trim((string) ($request->input('device_id') ?: $request->header('X-Device-Id')));
        $device = $deviceId !== ''
            ? WorkerAllowedDevice::where('company_id', $user->company_id)
                ->where('user_id', $user->id)
                ->where('device_id', $deviceId)
                ->first()
            : null;

        return response()->json([
            'success' => true,
            'data' => [
                'is_company_admin' => $this->isCompanyAdmin($user),
                'mobile_access_allowed' => $officeAccessGuard->isMobileAccessAllowed($user),
                'setting' => $setting,
                'device' => $device,
                'emergency_override_active' => (bool) ($setting?->hasActiveEmergencyOverride()),
                'access' => [
                    'allowed' => (bool) $decision['allowed'],
                    'message' => $decision['message'],
                    'reason' => $decision['reason'],
                    'distance_meters' => $decision['distance_meters'],
                    'allowed_radius_meters' => $decision['allowed_radius_meters'],
                ],
            ],
        ]);
    }

    private function setting(Request $request): CompanyOfficeAccessSetting
    {
        return CompanyOfficeAccessSetting::firstOrCreate(
            ['company_id' => $request->user()->company_id],
            [
                'geo_enabled' => false,
                'device_approval_enabled' => true,
                'allowed_radius_meters' => 100,
            ]
        );
    }

    private function setDeviceStatus(Request $request, WorkerAllowedDevice $device, string $status)
    {
        $this->authorizeCompanyAdmin($request);

        if ((int) $device->company_id !== (int) $request->user()->company_id) {
            abort(404);
        }

        $payload = ['status' => $status];

        if ($status === WorkerAllowedDevice::STATUS_APPROVED) {
            $payload['approved_at'] = now();
            $payload['approved_by'] = $request->user()->id;
        }

        if ($status !== WorkerAllowedDevice::STATUS_APPROVED) {
            $payload['approved_at'] = null;
            $payload['approved_by'] = null;
        }

        $device->forceFill($payload)->save();

        return response()->json([
            'success' => true,
            'message' => 'Device status updated successfully.',
            'data' => $device->fresh(['user:id,name,email,role', 'approvedBy:id,name']),
        ]);
    }

    private function setDeviceStatusByRequest(Request $request, string $status)
    {
        $this->authorizeCompanyAdmin($request);

        $validated = $request->validate([
            'user_id' => 'required|integer',
            'device_id' => 'required|string|max:191',
        ]);

        $device = WorkerAllowedDevice::where('company_id', $request->user()->company_id)
            ->where('user_id', (int) $validated['user_id'])
            ->where('device_id', $validated['device_id'])
            ->first();

        if (!$device) {
            return response()->json([
                'success' => false,
                'message' => 'Device not found for this company/user/device_id.',
                'code' => 'DEVICE_NOT_FOUND',
            ], 404);
        }

        return $this->setDeviceStatus($request, $device, $status);
    }

    private function authorizeCompanyAdmin(Request $request): void
    {
        if (!$this->isCompanyAdmin($request->user())) {
            abort(403, 'Only company admin can manage office access.');
        }
    }

    private function isCompanyAdmin($user): bool
    {
        return $user && (strtolower((string) $user->role) === 'company_admin' || $user->hasRole('company_admin'));
    }
}
