<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CompanyRoleController extends Controller
{
    public function index($slug, Request $request)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $roles = Role::where('company_id', $company->id);

            return DataTables::of($roles)
                ->addIndexColumn()
                ->addColumn('users_count', function ($role) use ($company) {
                    return $this->usersCountForRole((int) $role->id, (int) $company->id);
                })
                ->addColumn('action', function ($role) use ($company) {
                    $encryptedId = Crypt::encryptString($role->id);

                    $editUrl = route('company.roles.edit', [$company->slug, $encryptedId]);
                    $deleteUrl = route('company.roles.delete', [$company->slug, $encryptedId]);
                    $deleteBtn = $this->usersCountForRole((int) $role->id, (int) $company->id) > 0
                        ? '<span class="badge badge-danger">In Use</span>'
                        : '<form method="POST" action="' . $deleteUrl . '" style="display:inline">
                            ' . csrf_field() . method_field('DELETE') . '
                            <button class="btn btn-sm btn-danger" onclick="return confirm(\'Delete this role?\')">Delete</button>
                           </form>';

                    return '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a> ' . $deleteBtn;
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.roles.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureWebPermissions($company->id);
        $permissions = $this->groupedPermissions($company->id);

        return view('company.roles.create', compact('company', 'permissions'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'name' => [
                'required',
                Rule::unique('roles', 'name')
                    ->where(fn($q) => $q
                        ->where('company_id', $company->id)
                        ->where('guard_name', 'web')),
            ],
            'permissions' => 'required|array'
        ]);

        // NOTE:
        // Spatie Role::create() enforces global name+guard uniqueness and throws
        // RoleAlreadyExists before our company-scoped validation can apply.
        // Using query()->create() preserves our per-company uniqueness rule.
        $role = Role::query()->create([
            'name' => $request->name,
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);

        $role->syncPermissions($request->permissions);

        return redirect()
            ->route('company.roles.index', $company->slug)
            ->with('success', 'Role created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $roleId = Crypt::decryptString($encryptedId);

        $role = Role::where('id', $roleId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $this->ensureWebPermissions($company->id);
        $permissions = $this->groupedPermissions($company->id);
        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('company.roles.edit', compact('company', 'role', 'permissions', 'rolePermissions'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $roleId = decrypt($encryptedId);
        $role = Role::where('id', $roleId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $request->validate([
            'name' => [
                'required',
                Rule::unique('roles', 'name')
                    ->ignore($role->id)
                    ->where(fn($q) => $q
                        ->where('company_id', $company->id)
                        ->where('guard_name', 'web')),
            ],
            'permissions' => 'required|array'
        ]);

        $role->update(['name' => $request->name]);
        $role->syncPermissions($request->permissions);

        return redirect()
            ->route('company.roles.index', $company->slug)
            ->with('success', 'Role updated successfully');
    }

    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $roleId = Crypt::decryptString($encryptedId);

        $role = Role::where('id', $roleId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        if ($this->usersCountForRole((int) $role->id, (int) $company->id) > 0) {
            return back()->withErrors('Role is assigned to users and cannot be deleted.');
        }

        $role->delete();

        return back()->with('success', 'Role deleted successfully');
    }

    private function groupedPermissions(int $companyId)
    {
        $actionOrder = ['view' => 1, 'create' => 2, 'edit' => 3, 'delete' => 4, 'manage' => 5];
        $allowedActions = array_keys($actionOrder);

        return Permission::query()
            ->where('guard_name', 'web')
            ->where(function ($q) use ($companyId) {
                $q->whereNull('company_id')
                    ->orWhere('company_id', $companyId);
            })
            ->get()
            ->reject(function ($permission) {
                return in_array($this->extractModule($permission->name), $this->deprecatedPermissionModules(), true);
            })
            ->groupBy(function ($permission) {
                return $this->extractModule($permission->name);
            })
            ->map(function ($items, $module) use ($actionOrder) {
                $allowedActions = $this->actionsForModule((string) $module);

                return $items
                    ->filter(function ($permission) use ($allowedActions) {
                        return in_array($this->extractAction($permission->name), $allowedActions, true);
                    })
                    // When both legacy and canonical permission names exist for one module/action,
                    // keep only one so UI does not show duplicate checkboxes.
                    ->sortBy(function ($permission) {
                        $name = (string) $permission->name;

                        // Prefer canonical format like "module-action" over legacy "action-module".
                        return preg_match('/^(.+?)[\-\._ ](view|create|edit|delete|manage)$/i', $name) ? 0 : 1;
                    })
                    ->groupBy(function ($permission) {
                        return $this->extractAction($permission->name);
                    })
                    ->map(function ($group) {
                        return $group->first();
                    })
                    ->sortBy(function ($permission) use ($actionOrder) {
                    $action = $this->extractAction($permission->name);
                    return $actionOrder[$action] ?? 99;
                    })
                    ->values();
            })
            ->filter(fn($items) => $items->isNotEmpty())
            ->sortKeys();
    }

    private function usersCountForRole(int $roleId, int $companyId): int
    {
        $pivotTable = config('permission.table_names.model_has_roles', 'model_has_roles');

        return (int) DB::table($pivotTable)
            ->join('users', 'users.id', '=', "{$pivotTable}.model_id")
            ->where("{$pivotTable}.role_id", $roleId)
            ->where("{$pivotTable}.model_type", User::class)
            ->where('users.company_id', $companyId)
            ->distinct()
            ->count('users.id');
    }

    private function ensureWebPermissions(int $companyId): void
    {
        $this->normalizeLegacyPermissionNames();

        $defaultModules = [
            'dashboard',
            'user',
            'role',
            'permission',
            'notification',
            'app-theme',
            'customer',
            'job-worker',
            'jobwork-issue',
            'item',
            'item-set',
            'label-config',
            'label-print',
            'other-charge',
            'production-cost',
            'labour-formula',
            'production-step',
            'vacuum-buch',
            'vacuum-process',
            'vacuum-voucher',
            'vacuum-live-dashboard',
            'casting-heating',
            'casting-metal-issue',
            'casting-release',
            'tree-cutting-issue',
            'tree-cutting-receive',
            'casting-sorting',
            'voucher-history',
            'sale',
            'sale-advance',
            'approval',
            'approval-return',
            'report-sales-summary',
            'report-purchase-receiver-summary',
            'report-stock-position',
            'report-approval-outstanding',
            'report-outstanding-amount',
            'report-barcode-history',
            'report-worker-loss',
            'report-visiting-cards',
        ];

        foreach ($defaultModules as $module) {
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

    private function normalizeLegacyPermissionNames(): void
    {
        $legacyToCanonical = [
            'item-set-set' => 'item-set-view',
            'label-print-print' => 'label-print-view',
            'label-config-config' => 'label-config-view',
            'other-charge-charge' => 'other-charge-view',
        ];

        foreach ($legacyToCanonical as $legacy => $canonical) {
            $legacyPermission = Permission::where('guard_name', 'web')->where('name', $legacy)->first();
            if (!$legacyPermission) {
                continue;
            }

            $canonicalPermission = Permission::where('guard_name', 'web')->where('name', $canonical)->first();

            if ($canonicalPermission) {
                DB::table('role_has_permissions')
                    ->where('permission_id', $legacyPermission->id)
                    ->update(['permission_id' => $canonicalPermission->id]);

                $legacyPermission->delete();
                continue;
            }

            $legacyPermission->name = $canonical;
            $legacyPermission->save();
        }
    }

    private function deprecatedPermissionModules(): array
    {
        return ['return'];
    }

    private function actionsForModule(string $module): array
    {
        return in_array($module, ['dashboard', 'notification', 'vacuum-live-dashboard'], true)
            ? ['view']
            : ['view', 'create', 'edit', 'delete', 'manage'];
    }

    private function extractModule(string $permissionName): string
    {
        // Handles legacy style: create-user, edit-sale, view-approval
        if (preg_match('/^(view|create|edit|delete|manage)[\-\._ ](.+)$/i', $permissionName, $m)) {
            return Str::lower($m[2]);
        }

        // Handles style: user-create, sale-edit, approval-view
        if (preg_match('/^(.+?)[\-\._ ](view|create|edit|delete|manage)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        return Str::lower($permissionName);
    }

    private function extractAction(string $permissionName): string
    {
        // Handles legacy style: create-user, edit-sale, view-approval
        if (preg_match('/^(view|create|edit|delete|manage)[\-\._ ](.+)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        // Handles style: user-create, sale-edit, approval-view
        if (preg_match('/(view|create|edit|delete|manage)$/i', $permissionName, $m)) {
            return Str::lower($m[1]);
        }

        return $permissionName;
    }
}
