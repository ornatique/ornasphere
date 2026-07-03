<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $permissions = Permission::query()
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
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $permissions,
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
        return ['return'];
    }
}
