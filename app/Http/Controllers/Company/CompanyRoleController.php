<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
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
            $roles = Role::where('company_id', $company->id)->withCount('users');

            return DataTables::of($roles)
                ->addIndexColumn()
                ->addColumn('action', function ($role) use ($company) {
                    $encryptedId = Crypt::encryptString($role->id);

                    $editUrl = route('company.roles.edit', [$company->slug, $encryptedId]);
                    $deleteBtn = $role->users_count > 0
                        ? '<span class="badge badge-danger">In Use</span>'
                        : '<button data-id="' . $encryptedId . '" class="btn btn-sm btn-danger deleteRole">Delete</button>';

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
        $this->ensureWebPermissions();
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

        $this->ensureWebPermissions();
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
            ->withCount('users')
            ->firstOrFail();

        if ($role->users_count > 0) {
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
            ->get()
            ->groupBy(function ($permission) {
                return $this->extractModule($permission->name);
            })
            ->map(function ($items) use ($actionOrder, $allowedActions) {
                return $items
                    ->filter(function ($permission) use ($allowedActions) {
                        return in_array($this->extractAction($permission->name), $allowedActions, true);
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

    private function ensureWebPermissions(): void
    {
        $this->normalizeLegacyPermissionNames();

        $defaultModules = [
            'dashboard',
            'user',
            'role',
            'permission',
            'customer',
            'item',
            'item-set',
            'label-config',
            'label-print',
            'other-charge',
            'sale',
            'approval',
            'return',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'manage'];

        foreach ($defaultModules as $module) {
            foreach ($actions as $action) {
                if ($module === 'dashboard' && $action !== 'view') {
                    continue;
                }

                Permission::firstOrCreate([
                    'name' => "{$module}-{$action}",
                    'guard_name' => 'web',
                ], [
                    'company_id' => null,
                ]);
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
