<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Http\Request;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;

class CompanyRoleController extends Controller
{
    public function index($slug, Request $request)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $roles = Role::where('company_id', $company->id)
                ->withCount('users'); // IMPORTANT for delete protection

            return DataTables::of($roles)
                ->addIndexColumn()

                ->addColumn('action', function ($role) use ($company) {

                    $encryptedId = Crypt::encryptString($role->id);

                    $editUrl = route('company.roles.edit', [
                        $company->slug,
                        $encryptedId
                    ]);

                    $deleteBtn = $role->users_count > 0
                        ? '<span class="badge badge-danger">In Use</span>'
                        : '<button data-id="' . $encryptedId . '"
                               class="btn btn-sm btn-danger deleteRole">
                               Delete
                       </button>';

                    return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                    ' . $deleteBtn . '
                ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.roles.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissions = Permission::where('company_id', $company->id)
            ->get()
            ->groupBy(function ($permission) {
                return explode('-', $permission->name)[0];
            });

        return view('company.roles.create', compact('company', 'permissions'));
    }



    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'name' => 'required|unique:roles,name',
            'permissions' => 'required|array'
        ]);

        $role = Role::create([
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

        // Group permissions by module (user-create → user)
        $permissions = Permission::where('company_id', $company->id)
            ->get()
            ->groupBy(function ($permission) {
                return explode('-', $permission->name)[0];
            });

        $rolePermissions = $role->permissions->pluck('name')->toArray();

        return view('company.roles.edit', compact(
            'company',
            'role',
            'permissions',
            'rolePermissions'
        ));
    }


    public function update(Request $request, $slug, $encryptedId)
    {

        $company = Company::whereSlug($slug)->firstOrFail();

        $roleId = decrypt($encryptedId);
        $role = Role::where('id', $roleId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required',
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
}
