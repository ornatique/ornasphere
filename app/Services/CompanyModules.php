<?php

namespace App\Services;

use App\Models\Company;
use App\Models\Permission;
use Illuminate\Http\Request;

class CompanyModules
{
    public static function allowed(?Company $company): array
    {
        if (!$company) return [];
        if ($company->parent_company_id) {
            $company = $company->moduleParent;
            if (!$company) return [];
        }
        return $company->allowed_modules ?? array_keys(config('company_modules'));
    }

    public static function enabled(?Company $company, ?string $module): bool
    {
        return $module === null || in_array($module, self::allowed($company), true);
    }

    public static function permissionModule(string $name): ?string
    {
        $name = strtolower(str_replace(['_', '.', ' '], '-', $name));
        $name = preg_replace('/^(view|create|edit|delete|manage)-|-(view|create|edit|delete|manage)$/', '', $name);
        foreach (config('company_modules') as $key => $definition) {
            foreach ($definition['permissions'] as $permission) {
                if (str_replace('-', '', $name) === str_replace('-', '', $permission)) return $key;
            }
        }
        return null;
    }

    public static function permissionAllowed(?Company $company, string $name): bool
    {
        return self::enabled($company, self::permissionModule($name));
    }

    public static function permissionIds(?Company $company): array
    {
        return Permission::where('guard_name', 'web')->get(['id', 'name'])
            ->filter(fn ($p) => self::permissionAllowed($company, $p->name))->pluck('id')->all();
    }

    public static function requestModule(Request $request): ?string
    {
        $route = $request->route();
        if (!$route) return null;
        if ($request->routeIs('company.reports.visiting-cards.*')) return 'visiting-cards';
        if ($request->routeIs('company.customers.gst-search.*')) return 'gst-search';
        $controller = class_basename(explode('@', $route->getActionName())[0]);
        $controller = preg_replace('/(?:Api)?Controller$/', '', $controller);
        foreach (config('company_modules') as $key => $definition) {
            if (in_array($controller, $definition['controllers'], true)) return $key;
        }
        // Reuse the web permission map for auxiliary endpoints.
        $permission = (new \App\Http\Middleware\CompanyRoutePermission)->moduleFromRouteName((string) $route->getName());
        return $permission ? self::permissionModule($permission) : null;
    }

    public static function enforce(Request $request): void
    {
        $company = $request->user()?->company;
        if (!$company) return;
        $denied = !self::enabled($company, self::requestModule($request));
        // Check submitted permission IDs (API) and names (web) before assignment.
        if (!$request->isMethod('GET') && $request->has('permissions')) {
            foreach ((array) $request->input('permissions') as $value) {
                if (!is_scalar($value)) continue; // Controller validation handles malformed input.
                $name = is_numeric($value) ? Permission::whereKey($value)->value('name') : $value;
                if ($name && !self::permissionAllowed($company, (string) $name)) $denied = true;
            }
        }
        if ($request->isMethod('POST') || $request->isMethod('PUT') || $request->isMethod('PATCH')) {
            if (str_contains((string) $request->route()?->getActionName(), 'PermissionController') || str_contains((string) $request->route()?->getActionName(), 'PermissionApiController')) {
                if (is_string($request->input('name')) && !self::permissionAllowed($company, $request->input('name'))) $denied = true;
            }
        }
        if (!$denied) return;
        $message = 'This module is not enabled for your company. Please contact your administrator.';
        if ($request->expectsJson() || $request->is('api/*')) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json([
                'success' => false, 'code' => 'MODULE_NOT_ENABLED', 'message' => $message,
            ], 403));
        }
        abort(403, $message);
    }
}
