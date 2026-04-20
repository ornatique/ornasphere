<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Permission;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;

class CompanyPermissionController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureWebPermissions();

        if ($request->ajax()) {
            $permissions = Permission::where('guard_name', 'web')
                ->withCount('roles');

            return DataTables::of($permissions)
                ->addIndexColumn()

                ->addColumn('roles_count', function ($permission) {
                    return $permission->roles_count;
                })

                ->addColumn('action', function ($permission) use ($company) {
                    $encryptedId = encrypt($permission->id);

                    $editUrl = route('company.permissions.edit', [
                        $company->slug,
                        $encryptedId
                    ]);

                    $deleteUrl = route('company.permissions.destroy', [
                        $company->slug,
                        $encryptedId
                    ]);

                    $deleteBtn = $permission->roles_count > 0
                        ? '<button class="btn btn-sm btn-secondary" disabled>In Use</button>'
                        : '<form method="POST" action="' . $deleteUrl . '" style="display:inline">
                        ' . csrf_field() . method_field('DELETE') . '
                        <button class="btn btn-sm btn-danger"
                            onclick="return confirm(\'Delete this permission?\')">
                            Delete
                        </button>
                       </form>';

                    return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    ' . $deleteBtn . '
                ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.permissions.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureWebPermissions();

        return view('company.permissions.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'name' => 'required|string'
        ]);

        $name = $this->normalizePermissionName(trim((string) $request->name));
        $existing = Permission::where('name', $name)
            ->where('guard_name', 'web')
            ->first();

        if ($existing) {
            return redirect()
                ->route('company.permissions.index', $company->slug)
                ->with('success', 'Permission already exists, using existing permission.');
        }

        Permission::create([
            'name' => $name,
            'guard_name' => 'web',
            'company_id' => null,
        ]);

        return redirect()
            ->route('company.permissions.index', $company->slug)
            ->with('success', 'Permission created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)->firstOrFail();

        return view('company.permissions.edit', compact('company', 'permission'));
    }


    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)->firstOrFail();

        $request->validate([
            'name' => 'required|string'
        ]);

        $newName = $this->normalizePermissionName(trim((string) $request->name));
        $exists = Permission::where('name', $newName)
            ->where('guard_name', 'web')
            ->where('id', '!=', $permission->id)
            ->exists();

        if ($exists) {
            return back()->withErrors('Permission name already exists.')->withInput();
        }

        $permission->update(['name' => $newName]);

        return redirect()
            ->route('company.permissions.index', $company->slug)
            ->with('success', 'Permission updated');
    }


    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)->firstOrFail();

        if ($permission->roles()->count() > 0) {
            return back()->withErrors('Permission is assigned to roles and cannot be deleted.');
        }

        $permission->delete();

        return back()->with('success', 'Permission deleted successfully');
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
            'report-sales-summary',
            'report-stock-position',
            'report-approval-outstanding',
            'report-barcode-history',
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
                \DB::table('role_has_permissions')
                    ->where('permission_id', $legacyPermission->id)
                    ->update(['permission_id' => $canonicalPermission->id]);

                $legacyPermission->delete();
                continue;
            }

            $legacyPermission->name = $canonical;
            $legacyPermission->save();
        }
    }

    private function normalizePermissionName(string $name): string
    {
        $name = strtolower(trim($name));
        $name = preg_replace('/\s+/', '-', $name);
        $name = preg_replace('/_+/', '-', $name);
        $name = preg_replace('/-+/', '-', $name);

        return $name;
    }
}
