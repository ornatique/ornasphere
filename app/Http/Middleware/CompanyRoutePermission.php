<?php

namespace App\Http\Middleware;

use App\Models\Permission;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class CompanyRoutePermission
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || !$user->company_id) {
            return $next($request);
        }

        // Company admin keeps full access by default.
        if ($user->hasRole('company_admin')) {
            return $next($request);
        }

        $routeName = (string) optional($request->route())->getName();
        if ($routeName === '' || !Str::startsWith($routeName, 'company.')) {
            return $next($request);
        }

        $module = $this->moduleFromRouteName($routeName);
        if (!$module) {
            return $next($request);
        }

        $action = $this->actionFromRouteName($routeName, $request);
        $moduleVariants = array_unique([
            $module,
            str_replace('-', '', $module),
            str_replace('-', '_', $module),
            str_replace('-', '.', $module),
            str_replace('-', ' ', $module),
        ]);

        $candidates = [];
        foreach ($moduleVariants as $m) {
            $candidates[] = "{$m}-{$action}";
            $candidates[] = "{$m}.{$action}";
            $candidates[] = "{$m}_{$action}";
            $candidates[] = "{$m} {$action}";
            $candidates[] = "{$action}-{$m}";
            $candidates[] = "{$action}.{$m}";
            $candidates[] = "{$action}_{$m}";
            $candidates[] = "{$action} {$m}";
        }
        $candidates = array_values(array_unique($candidates));

        if ($user->hasAnyPermission($candidates)) {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this module.');
    }

    private function moduleFromRouteName(string $routeName): ?string
    {
        $map = [
            'company.users.' => 'user',
            'company.customers.' => 'customer',
            'company.job-workers.' => 'job-worker',
            'company.jobwork-issue.' => 'jobwork-issue',
            'company.items.' => 'item',
            'company.label_config.' => 'label-config',
            'company.label.print' => 'label-print',
            'company.label.generate' => 'label-print',
            'company.other-charge.' => 'other-charge',
            'company.production-cost.' => 'production-cost',
            'company.labour-formula.' => 'labour-formula',
            'company.production-step.' => 'production-step',
            'company.item_sets.' => 'item-set',
            'company.itemsets.' => 'item-set',
            'company.list_itemset' => 'item-set',
            'company.get-item-details' => 'item-set',
            'company.sales.' => 'sale',
            'company.returns.' => 'return',
            'company.roles.' => 'role',
            'company.permissions.' => 'permission',
            'company.approval-sales.' => 'sale',
            'company.approval.return.' => 'approval',
            'company.approval.' => 'approval',
            'company.reports.sales-summary.' => 'report-sales-summary',
            'company.reports.stock-position.' => 'report-stock-position',
            'company.reports.approval-outstanding.' => 'report-approval-outstanding',
            'company.reports.barcode-history.' => 'report-barcode-history',
        ];

        foreach ($map as $prefix => $module) {
            if (Str::startsWith($routeName, $prefix)) {
                return $module;
            }
        }

        return null;
    }

    private function actionFromRouteName(string $routeName, Request $request): string
    {
        $lastSegment = Str::afterLast($routeName, '.');
        $verb = strtoupper($request->method());

        $viewActions = ['index', 'data', 'list', 'list_data', 'show', 'view', 'qrcode', 'qrList', 'qrImage', 'search'];
        $createActions = ['create', 'store', 'generate', 'finalize', 'processSelected', 'sale', 'return', 'fromApproval', 'options'];
        $editActions = ['edit', 'update', 'saveCell'];
        $deleteActions = ['delete', 'destroy', 'toggle', 'remove'];

        if (in_array($lastSegment, $createActions, true) || $verb === 'POST') {
            return 'create';
        }

        if (in_array($lastSegment, $editActions, true) || in_array($verb, ['PUT', 'PATCH'], true)) {
            return 'edit';
        }

        if (in_array($lastSegment, $deleteActions, true) || $verb === 'DELETE') {
            return 'delete';
        }

        if (in_array($lastSegment, $viewActions, true) || $verb === 'GET') {
            return 'view';
        }

        return 'view';
    }
}
