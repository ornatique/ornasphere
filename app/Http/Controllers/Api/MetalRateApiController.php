<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LiveMetalRateService;

class MetalRateApiController extends Controller
{
    public function index()
    {
        $payload = $this->metalRateService()->rates();
        $rates = $payload['rates'] ?? [];

        return response()
            ->json([
                'success' => (bool) ($payload['enabled'] ?? false),
                'message' => $payload['message'] ?? 'Live metal rates fetched successfully.',
                'source' => $payload['source'] ?? 'JK Sons',
                'updated_at' => $payload['updated_at'] ?? null,
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
