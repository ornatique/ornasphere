<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
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
            'data' => tap($role->load('permissions:id,name'), fn ($loadedRole) => $this->removeDeprecatedPermissionsFromRole($loadedRole)),
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
            'data' => tap($role->load('permissions:id,name'), fn ($loadedRole) => $this->removeDeprecatedPermissionsFromRole($loadedRole)),
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
        return ['return'];
    }
}
