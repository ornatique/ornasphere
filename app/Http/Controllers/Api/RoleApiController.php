<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RoleApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $roles = Role::query()
            ->where('guard_name', 'web')
            ->where('company_id', $companyId)
            ->with('permissions:id,name')
            ->orderByDesc('id')
            ->get();

        $roles->each(fn ($role) => $this->removeDeprecatedPermissionsFromRole($role));
        $roles->each(fn ($role) => $this->attachPermissionGroups($role, $companyId));
        $this->appendUsersCount($roles, $companyId);

        return response()->json([
            'success' => true,
            'data' => $roles,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('roles', 'name')->where(fn ($q) => $q
                    ->where('guard_name', 'web')
                    ->where('company_id', $companyId)),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer'],
        ]);

        $role = Role::query()->create([
            'name' => $validated['name'],
            'guard_name' => 'web',
            'company_id' => $companyId,
        ]);

        $permissionIds = collect($validated['permissions'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->values();

        if ($permissionIds->isNotEmpty()) {
            $allowedPermissionIds = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('id', $permissionIds)
                ->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                })
                ->where(function ($q) {
                    foreach ($this->deprecatedPermissionModules() as $module) {
                        $q->where('name', 'not like', "{$module}-%");
                    }
                })
                ->pluck('id')
                ->all();

            $role->syncPermissions($allowedPermissionIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => tap($role->load('permissions:id,name'), function ($loadedRole) use ($companyId) {
                $this->removeDeprecatedPermissionsFromRole($loadedRole);
                $this->attachPermissionGroups($loadedRole, $companyId);
            }),
        ], 201);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->with('permissions:id,name')
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $role->setAttribute('users_count', $this->getUsersCountForRole((int) $role->id, $companyId));

        $this->removeDeprecatedPermissionsFromRole($role);
        $this->attachPermissionGroups($role, $companyId);
        return response()->json([
            'success' => true,
            'data' => $role,
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn ($q) => $q
                        ->where('guard_name', 'web')
                        ->where('company_id', $companyId)),
            ],
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['integer'],
        ]);

        $role->update(['name' => $validated['name']]);

        if (array_key_exists('permissions', $validated)) {
            $permissionIds = collect($validated['permissions'] ?? [])
                ->map(fn ($permissionId) => (int) $permissionId)
                ->values();

            $allowedPermissionIds = Permission::query()
                ->where('guard_name', 'web')
                ->whereIn('id', $permissionIds)
                ->where(function ($q) use ($companyId) {
                    $q->whereNull('company_id')
                        ->orWhere('company_id', $companyId);
                })
                ->where(function ($q) {
                    foreach ($this->deprecatedPermissionModules() as $module) {
                        $q->where('name', 'not like', "{$module}-%");
                    }
                })
                ->pluck('id')
                ->all();

            $role->syncPermissions($allowedPermissionIds);
        }

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => tap($role->load('permissions:id,name'), function ($loadedRole) use ($companyId) {
                $this->removeDeprecatedPermissionsFromRole($loadedRole);
                $this->attachPermissionGroups($loadedRole, $companyId);
            }),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $role = Role::query()
            ->where('guard_name', 'web')
            ->where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found.',
            ], 404);
        }

        if ($this->getUsersCountForRole((int) $role->id, $companyId) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Role is assigned to users and cannot be deleted.',
            ], 422);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully',
        ]);
    }

    private function appendUsersCount($roles, int $companyId): void
    {
        $roleIds = $roles->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (empty($roleIds)) {
            return;
        }

        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        $counts = DB::table($pivotTable)
            ->select('role_id', DB::raw('COUNT(DISTINCT model_id) as users_count'))
            ->join('users', 'users.id', '=', "{$pivotTable}.model_id")
            ->whereIn('role_id', $roleIds)
            ->where('model_type', User::class)
            ->where('users.company_id', $companyId)
            ->groupBy('role_id')
            ->pluck('users_count', 'role_id');

        foreach ($roles as $role) {
            $role->setAttribute('users_count', (int) ($counts[(int) $role->id] ?? 0));
        }
    }

    private function getUsersCountForRole(int $roleId, int $companyId): int
    {
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        return (int) DB::table($pivotTable)
            ->join('users', 'users.id', '=', "{$pivotTable}.model_id")
            ->where('role_id', $roleId)
            ->where('model_type', User::class)
            ->where('users.company_id', $companyId)
            ->distinct()
            ->count('users.id');
    }

    private function removeDeprecatedPermissionsFromRole(Role $role): void
    {
        if (!$role->relationLoaded('permissions')) {
            return;
        }

        $role->setRelation('permissions', $role->permissions
            ->reject(fn ($permission) => $this->isDeprecatedPermission((string) $permission->name))
            ->values());
    }

    private function attachPermissionGroups(Role $role, int $companyId): void
    {
        $permissions = $this->availablePermissions($companyId);
        $selectedPermissionIds = $role->relationLoaded('permissions')
            ? $role->permissions->pluck('id')->map(fn ($id) => (int) $id)->all()
            : [];

        $role->setAttribute('permission_groups', $this->permissionGroups($permissions, $selectedPermissionIds));
    }

    private function availablePermissions(int $companyId): Collection
    {
        $this->ensureWebPermissions($companyId);

        $permissions = Permission::query()
            ->where('guard_name', 'web')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->get()
            ->reject(fn ($permission) => $this->isDeprecatedPermission((string) $permission->name));

        return $this->orderedPermissions($permissions);
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
            'qr-making' => 'QR Making',
            'approval' => 'Sale on Approval',
            'approval-return' => 'Approval Return',
            'sale' => 'Sales Estimate',
            'sale-advance' => 'Receive / Return / Purchase',
            'report-sales-summary' => 'Sales Summary',
            'report-purchase-receiver-summary' => 'Purchase / Receiver Summary',
            'report-stock-position' => 'Stock Position',
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
            'qr-making' => 'QR Making',
            'approval' => 'Sales',
            'approval-return' => 'Sales',
            'sale' => 'Sales',
            'sale-advance' => 'Sales',
            'report-sales-summary' => 'Reports',
            'report-purchase-receiver-summary' => 'Reports',
            'report-stock-position' => 'Reports',
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

    private function isDeprecatedPermission(string $permissionName): bool
    {
        foreach ($this->deprecatedPermissionModules() as $module) {
            if (str_starts_with($permissionName, "{$module}-")) {
                return true;
            }
        }

        return false;
    }

    private function deprecatedPermissionModules(): array
    {
        return ['return', 'customer', 'job-worker'];
    }
}
