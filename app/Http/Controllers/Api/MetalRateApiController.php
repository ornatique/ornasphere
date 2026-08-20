<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveMetalRateService;
use Illuminate\Http\Request;

class MetalRateApiController extends Controller
{
    public function index(Request $request)
    {
        $payload = $this->metalRateService()->rates();
        $rates = $payload['rates'] ?? [];
        $user = $request->user();
        $officeAccess = $request->attributes->get('office_access_decision');

        return response()
            ->json([
                'success' => (bool) ($payload['enabled'] ?? false),
                'message' => $payload['message'] ?? 'Live metal rates fetched successfully.',
                'source' => $payload['source'] ?? 'JK Sons',
                'updated_at' => $payload['updated_at'] ?? null,
                'user_id' => $user?->id,
                'company_id' => $user?->company_id,
                'request_device_id' => $request->input('device_id') ?: $request->input('deviceId') ?: $request->header('X-Device-Id') ?: $request->header('Device-Id') ?: $request->header('DeviceId'),
                'request_latitude' => $request->input('latitude') ?: $request->header('X-Latitude') ?: $request->header('Latitude'),
                'request_longitude' => $request->input('longitude') ?: $request->header('X-Longitude') ?: $request->header('Longitude'),
                'office_access' => $officeAccess ? [
                    'allowed' => (bool) ($officeAccess['allowed'] ?? false),
                    'reason' => $officeAccess['reason'] ?? null,
                    'distance_meters' => $officeAccess['distance_meters'] ?? null,
                    'allowed_radius_meters' => $officeAccess['allowed_radius_meters'] ?? null,
                    'device_id' => $officeAccess['device_id'] ?? null,
                    'device_status' => $officeAccess['device_status'] ?? null,
                    'device_row_id' => $officeAccess['device_row_id'] ?? null,
                ] : null,
                'data' => $rates,
                'rates' => $rates,
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }

    private function metalRateService()
    {
        $serviceClass = LiveMetalRateService::class;
        if (!class_exists($serviceClass)) {
            $servicePath = app_path('Services/LiveMetalRateService.php');
            if (is_file($servicePath)) {
                require_once $servicePath;
            }
        }

        return app($serviceClass);
    }
}
