<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        App\Providers\FortifyServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {

        $middleware->alias([
            'superadmin.2fa' => \App\Http\Middleware\ForceSuperAdmin2FA::class,
            'superadmin.ip'  => \App\Http\Middleware\SuperAdminIPWhitelist::class,
            'company.2fa' => \App\Http\Middleware\Company2FA::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'auth' => \App\Http\Middleware\Authenticate::class,
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
            'company.route.permission' => \App\Http\Middleware\CompanyRoutePermission::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $isApi = static function (Request $request): bool {
            return $request->expectsJson() || $request->is('api/*');
        };

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null; // keep default web redirect with validation errors
            }

            $firstError = collect($e->errors())->flatten()->first() ?: 'Validation failed.';

            return response()->json([
                'success' => false,
                'message' => $firstError,
                'code' => 'VALIDATION_ERROR',
                'errors' => $e->errors(),
            ], 422);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Requested data not found.',
                'code' => 'NOT_FOUND',
            ], 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Route not found.',
                'code' => 'ROUTE_NOT_FOUND',
            ], 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'HTTP method not allowed for this route.',
                'code' => 'METHOD_NOT_ALLOWED',
            ], 405);
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (!$isApi($request)) {
                return null;
            }

            return response()->json([
                'success' => false,
                'message' => 'Unauthenticated.',
                'code' => 'UNAUTHENTICATED',
            ], 401);
        });

        $exceptions->render(function (QueryException $e, Request $request) use ($isApi) {
            Log::error('Database query exception', [
                'message' => $e->getMessage(),
                'sql' => $e->getSql(),
                'bindings' => $e->getBindings(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => optional($request->user())->id,
            ]);

            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Database error occurred. Please try again.',
                    'code' => 'DB_ERROR',
                ], 500);
            }

            if ($request->isMethod('get')) {
                return response('Something went wrong. Please try again later.', 500);
            }

            return back()->withInput()->with('error', 'Database error occurred. Please try again.');
        });

        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            Log::error('Unhandled exception', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => optional($request->user())->id,
            ]);

            if ($isApi($request)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Something went wrong. Please contact support.',
                    'code' => 'SERVER_ERROR',
                ], 500);
            }

            if ($request->isMethod('get')) {
                return response('Something went wrong. Please try again later.', 500);
            }

            return back()->withInput()->with('error', 'Something went wrong. Please try again.');
        });
    })
    ->create();
