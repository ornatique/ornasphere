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

        if ($request->ajax()) {
            $permissions = Permission::where('company_id', $company->id)
                ->withCount('roles'); // IMPORTANT

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

        return view('company.permissions.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'name' => 'required|string'
        ]);

        Permission::create([
            'name' => $request->name,
            'guard_name' => 'web',
            'company_id' => $company->id,
        ]);

        return redirect()
            ->route('company.permissions.index', $company->slug)
            ->with('success', 'Permission created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.permissions.edit', compact('company', 'permission'));
    }


    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $request->validate([
            'name' => 'required|string'
        ]);

        $permission->update(['name' => $request->name]);

        return redirect()
            ->route('company.permissions.index', $company->slug)
            ->with('success', 'Permission updated');
    }


    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $permissionId = decrypt($encryptedId);

        $permission = Permission::where('id', $permissionId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        if ($permission->roles()->count() > 0) {
            return back()->withErrors('Permission is assigned to roles and cannot be deleted.');
        }

        $permission->delete();

        return back()->with('success', 'Permission deleted successfully');
    }
}
