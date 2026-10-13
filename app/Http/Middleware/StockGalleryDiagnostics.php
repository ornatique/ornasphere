<?php

namespace App\Http\Middleware;

use App\Support\StockGalleryLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StockGalleryDiagnostics
{
    public function handle(Request $request, Closure $next)
    {
        $started = microtime(true);
        $id = (string) $request->header('X-Gallery-Request-Id');
        if (!Str::isUuid($id)) $id = (string) Str::uuid();
        $context = ['request_id' => $id, 'route' => $request->route()?->getName(),
            'method' => $request->method(), 'user_id' => $request->user()?->id,
            'company_id' => $request->user()?->company_id];
        StockGalleryLog::write('request.started', $context);
        try {
            $response = $next($request);
            StockGalleryLog::write('request.completed', $context + ['status' => $response->getStatusCode(),
                'duration_ms' => round((microtime(true) - $started) * 1000)]);
            return $response->header('X-Gallery-Request-Id', $id);
        } catch (\Throwable $exception) {
            StockGalleryLog::write('request.failed', $context + ['exception' => get_class($exception),
                'duration_ms' => round((microtime(true) - $started) * 1000)]);
            throw $exception;
        }
    }
}
