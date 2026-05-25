<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NormalizeAuthorizationHeader
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->headers->has('Authorization')) {
            $serverBag = $request->server;
            $getServerValue = function (string $key) use ($request, $serverBag) {
                if (is_object($serverBag) && method_exists($serverBag, 'get')) {
                    return $serverBag->get($key);
                }

                return $_SERVER[$key] ?? null;
            };

            $candidates = [
                $getServerValue('HTTP_AUTHORIZATION'),
                $getServerValue('REDIRECT_HTTP_AUTHORIZATION'),
                $getServerValue('X_HTTP_AUTHORIZATION'),
                $getServerValue('Authorization'),
            ];

            foreach ($candidates as $value) {
                if (is_string($value) && $value !== '') {
                    $request->headers->set('Authorization', $value);
                    break;
                }
            }
        }

        return $next($request);
    }
}
