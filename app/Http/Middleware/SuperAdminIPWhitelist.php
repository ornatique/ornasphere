<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\IpUtils;

class SuperAdminIPWhitelist
{
    public function handle(Request $request, Closure $next)
    {
        $allowedIps = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('SUPERADMIN_ALLOWED_IPS', '127.0.0.1,::1'))
        )));

        $clientIp = $this->resolveClientIp($request);

        if (!$this->isAllowed($clientIp, $allowedIps)) {
            Log::warning('SuperAdmin IP blocked', [
                'resolved_ip' => $clientIp,
                'request_ip' => $request->ip(),
                'cf_connecting_ip' => $request->header('CF-Connecting-IP'),
                'x_forwarded_for' => $request->header('X-Forwarded-For'),
                'x_real_ip' => $request->header('X-Real-IP'),
                'allowed_ips' => $allowedIps,
                'url' => $request->fullUrl(),
            ]);
            abort(403, 'Access denied from this IP');
        }

        return $next($request);
    }

    private function resolveClientIp(Request $request): string
    {
        // Prefer proxy/CDN headers first (Cloudflare / reverse proxy setups).
        $headerCandidates = [
            $request->header('CF-Connecting-IP'),
            $request->header('X-Forwarded-For'),
            $request->header('X-Real-IP'),
            $request->ip(),
        ];

        foreach ($headerCandidates as $value) {
            if (!$value) {
                continue;
            }

            // X-Forwarded-For can contain multiple IPs: client, proxy1, proxy2...
            $ips = array_map('trim', explode(',', (string) $value));

            foreach ($ips as $ip) {
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return (string) $request->ip();
    }

    private function isAllowed(string $clientIp, array $allowedIps): bool
    {
        if (!filter_var($clientIp, FILTER_VALIDATE_IP)) {
            return false;
        }

        foreach ($allowedIps as $allowed) {
            if (!$allowed) {
                continue;
            }

            // Supports single IP and CIDR notation.
            if (IpUtils::checkIp($clientIp, $allowed)) {
                return true;
            }
        }

        return false;
    }
}
