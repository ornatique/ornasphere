<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PermissionApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $this->ensureWebPermissions($companyId);

        $permissions = Permission::query()
            ->whereIn('id', \App\Services\CompanyModules::permissionIds($request->user()->company))
            ->where('guard_name', 'web')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->where(function ($q) {
                foreach ($this->deprecatedPermissionModules() as $module) {
                    $q->where('name', 'not like', "{$module}-%");
                }
            })
            ->withCount('roles')
            ->get();
        $permissions = $this->orderedPermissions($permissions);

        return response()->json([
            'success' => true,
            'data' => $permissions,
            'permission_groups' => $this->permissionGroups($permissions),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => ['required', 'string'],
        ]);

        $name = $this->normalizePermissionName($validated['name']);

        $exists = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', $name)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Permission already exists.',
            ], 422);
        }

        $permission = Permission::create([
            'name' => $name,
            'guard_name' => 'web',
            'company_id' => $companyId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Permission created successfully',
            'data' => $permission,
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('id', $id)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->withCount('roles')
            ->first();

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $permission,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('id', $id)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->first();

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => ['required', 'string'],
        ]);

        $name = $this->normalizePermissionName($validated['name']);

        $exists = Permission::query()
            ->where('guard_name', 'web')
            ->where('name', $name)
            ->where('id', '!=', $permission->id)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Permission name already exists.',
            ], 422);
        }

        $permission->update(['name' => $name]);

        return response()->json([
            'success' => true,
            'message' => 'Permission updated successfully',
            'data' => $permission,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $permission = Permission::query()
            ->where('guard_name', 'web')
            ->where('id', $id)
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->withCount('roles')
            ->first();

        if (!$permission) {
            return response()->json([
                'success' => false,
                'message' => 'Permission not found.',
            ], 404);
        }

        if ((int) $permission->roles_count > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Permission is assigned to roles and cannot be deleted.',
            ], 422);
        }

        $permission->delete();

        return response()->json([
            'success' => true,
            'message' => 'Permission deleted successfully',
        ]);
    }

    private function normalizePermissionName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/_+/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);

        return $name;
    }

    private function deprecatedPermissionModules(): array
    {
        return ['return', 'customer', 'job-worker'];
    }

    private function orderedPermissions(Collection $permissions): Collection
    {
        $order = $this->sidebarPermissionOrder();
        $actionOrder = ['view' => 1, 'create' => 2, 'edit' => 3, 'delete' => 4, 'manage' => 5];

        return $permissions
            ->sortBy(function ($permission) use ($order, $actionOrder) {
                $module = $this->extractModule((string) $permission->name);
                $action = $this->extractAction((string) $permission->name);

                return sprintf('%04d-%02d-%s', $order[$module] ?? 1000, $actionOrder[$action] ?? 99, $permission->name);
            })
            ->values();
    }

    private function permissionGroups(Collection $permissions, array $selectedPermissionIds = []): array
    {
        $selectedPermissionIds = array_map('intval', $selectedPermissionIds);
        $actionLabels = [
            'view' => 'View',
            'create' => 'Create',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'manage' => 'Manage',
        ];
        $moduleLabels = $this->permissionModuleLabels();
        $menuLabels = $this->permissionMenuLabels();

        return $permissions
            ->groupBy(fn ($permission) => $menuLabels[$this->extractModule((string) $permission->name)] ?? 'Other')
            ->map(function ($menuPermissions, $menuLabel) use ($moduleLabels, $actionLabels, $selectedPermissionIds) {
                $modules = $menuPermissions
                    ->groupBy(fn ($permission) => $this->extractModule((string) $permission->name))
                    ->map(function ($modulePermissions, $module) use ($moduleLabels, $actionLabels, $selectedPermissionIds) {
                        $actions = $modulePermissions
                            ->groupBy(fn ($permission) => $this->extractAction((string) $permission->name))
                            ->map(fn ($group) => $group->first())
                            ->map(function ($permission, $action) use ($actionLabels, $selectedPermissionIds) {
                                return [
                                    'action' => $action,
                                    'label' => $actionLabels[$action] ?? ucfirst((string) $action),
                                    'permission_id' => (int) $permission->id,
                                    'permission_name' => $permission->name,
                                    'checked' => in_array((int) $permission->id, $selectedPermissionIds, true),
                                ];
                            })
                            ->values();

                        $permissionIds = $actions->pluck('permission_id')->map(fn ($id) => (int) $id)->all();

                        return [
                            'module' => $module,
                            'label' => $moduleLabels[$module] ?? ucwords(str_replace(['-', '_', '.'], ' ', $module)),
                            'permission_ids' => $permissionIds,
                            'checked' => !empty($permissionIds) && empty(array_diff($permissionIds, $selectedPermissionIds)),
                            'actions' => $actions,
                        ];
                    })
                    ->values();

                $permissionIds = $modules->flatMap(fn ($module) => $module['permission_ids'])->map(fn ($id) => (int) $id)->all();

                return [
                    'menu' => Str::slug((string) $menuLabel),
                    'label' => (string) $menuLabel,
                    'permission_ids' => $permissionIds,
                    'checked' => !empty($permissionIds) && empty(array_diff($permissionIds, $selectedPermissionIds)),
                    'modules' => $modules,
                ];
            })
            ->values()
            ->all();
    }

    private function ensureWebPermissions(int $companyId): void
    {
        foreach ($this->defaultPermissionModules() as $module) {
            foreach ($this->actionsForModule($module) as $action) {
                $permission = Permission::firstOrCreate([
                    'name' => "{$module}-{$action}",
                    'guard_name' => 'web',
                ]);

                if ($permission->company_id !== null) {
                    $permission->company_id = null;
                    $permission->save();
                }
            }
        }
    }

    private function defaultPermissionModules(): array
    {
        return array_keys($this->permissionModuleLabels());
    }

    private function actionsForModule(string $module): array
    {
        if ($module === 'bullion') return ['view', 'create'];
        if ($module === 'report-approval-summary') return ['view'];
        if ($module === 'gst-search') return ['view'];
        return in_array($module, ['dashboard', 'notification', 'vacuum-live-dashboard'], true)
            ? ['view']
            : ['view', 'create', 'edit', 'delete', 'manage'];
    }

    private function sidebarPermissionOrder(): array
    {
        return array_flip($this->defaultPermissionModules());
    }

    private function permissionModuleLabels(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'vacuum-live-dashboard' => 'Production Monitor',
            'app-theme' => 'App Theme',
            'user' => 'Users',
            'office-access' => 'Office Access',
            'category-person' => 'Category Person',
            'person' => 'Person',
            'role' => 'Roles',
            'permission' => 'Permissions',
            'vacuum-buch' => 'Buch',
            'vacuum-process' => 'Process',
            'vacuum-voucher' => 'Vacuum',
            'casting-heating' => 'Casting Heating',
            'casting-metal-issue' => 'Casting Metal Issue',
            'casting-release' => 'Casting Receive',
            'tree-cutting-office' => 'Tree Cutting Issue Office',
            'tree-cutting-issue' => 'Tree Cutting Issue',
            'tree-cutting-receive' => 'Tree Cutting Receive',
            'casting-sorting' => 'Casting Sorting',
            'voucher-history' => 'Voucher History',
            'labour-formula' => 'Labour Formula',
            'production-cost' => 'Production Cost',
            'production-step' => 'Production Step',
            'jobwork-issue' => 'Jobwork Issue',
            'jobwork-receive' => 'Jobwork Receive',
            'item' => 'Items List',
            'label-config' => 'Label Config',
            'item-set' => 'Label Items',
            'label-print' => 'Label Printing',
            'other-charge' => 'Other Charges',
            'stock-gallery' => 'Stock Gallery',
            'bullion' => 'Bullion Management',
            'gst-search' => 'GST Search',
            'qr-making' => 'QR Making',
            'approval' => 'Sale on Approval',
            'approval-return' => 'Approval Return',
            'sale' => 'Sales Estimate',
            'sale-advance' => 'Receive / Return / Purchase',
            'report-sales-summary' => 'Sales Summary',
            'report-purchase-receiver-summary' => 'Purchase / Receiver Summary',
            'report-stock-position' => 'Stock Position',
            'report-approval-summary' => 'Approval Summary',
            'report-approval-outstanding' => 'Approval Outstanding',
            'report-outstanding-amount' => 'Outstanding Amount',
            'report-barcode-history' => 'Barcode History',
            'report-worker-loss' => 'Worker Loss Report',
            'report-visiting-cards' => 'Visiting Cards',
            'notification' => 'Notifications',
        ];
    }

    private function permissionMenuLabels(): array
    {
        return [
            'dashboard' => 'Dashboard',
            'vacuum-live-dashboard' => 'Production Monitor',
            'app-theme' => 'App Theme',
            'user' => 'User Management',
            'office-access' => 'User Management',
            'category-person' => 'User Management',
            'person' => 'User Management',
            'role' => 'User Management',
            'permission' => 'User Management',
            'vacuum-buch' => 'Casting',
            'vacuum-process' => 'Casting',
            'vacuum-voucher' => 'Casting',
            'casting-heating' => 'Casting',
            'casting-metal-issue' => 'Casting',
            'casting-release' => 'Casting',
            'tree-cutting-office' => 'Casting',
            'tree-cutting-issue' => 'Casting',
            'tree-cutting-receive' => 'Casting',
            'casting-sorting' => 'Casting',
            'voucher-history' => 'Casting',
            'labour-formula' => 'Jobwork',
            'production-cost' => 'Jobwork',
            'production-step' => 'Jobwork',
            'jobwork-issue' => 'Jobwork',
            'jobwork-receive' => 'Jobwork',
            'item' => 'Item Management',
            'label-config' => 'Item Management',
            'item-set' => 'Item Management',
            'label-print' => 'Item Management',
            'other-charge' => 'Item Management',
            'stock-gallery' => 'Stock Gallery',
            'bullion' => 'Bullion Management',
            'gst-search' => 'GST Search',
            'qr-making' => 'QR Making',
            'approval' => 'Sales',
            'approval-return' => 'Sales',
            'sale' => 'Sales',
            'sale-advance' => 'Sales',
            'report-sales-summary' => 'Reports',
            'report-purchase-receiver-summary' => 'Reports',
            'report-stock-position' => 'Reports',
            'report-approval-summary' => 'Approval Summary',
            'report-approval-outstanding' => 'Reports',
            'report-outstanding-amount' => 'Reports',
            'report-barcode-history' => 'Reports',
            'report-worker-loss' => 'Reports',
            'report-visiting-cards' => 'Visiting Cards',
            'notification' => 'Other',
        ];
    }

    private function extractModule(string $permissionName): string
    {
        if (preg_match('/^(view|create|edit|delete|manage)[\-\._ ](.+)$/i', $permissionName, $m)) {
            return Str::lower($m[2]);
        }

        if (preg_match('/^(.+?)[\-\._ ](view|create|edit|delete|manage)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        return Str::lower($permissionName);
    }

    private function extractAction(string $permissionName): string
    {
        if (preg_match('/^(view|create|edit|delete|manage)[\-\._ ](.+)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        if (preg_match('/(view|create|edit|delete|manage)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        return $permissionName;
    }
}
