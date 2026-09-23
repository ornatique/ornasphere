<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSingleActiveApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();

        if ($user && $token && $user->active_api_token_id && (int) $user->active_api_token_id !== (int) $token->id) {
            return $this->sessionReplacedResponse();
        }

        $requestDeviceId = (string) ($request->input('device_id') ?: $request->header('X-Device-Id'));
        if (
            $user
            && $token
            && $requestDeviceId !== ''
            && $user->active_device_id
            && !hash_equals((string) $user->active_device_id, $requestDeviceId)
        ) {
            return $this->sessionReplacedResponse();
        }

        return $next($request);
    }

    private function sessionReplacedResponse(): Response
    {
        return response()->json([
            'success' => false,
            'message' => 'Your account was logged in on another device. Please login again.',
            'code' => 'SESSION_REPLACED',
        ], 401);
    }
}
