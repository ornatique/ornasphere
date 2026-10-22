<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\{Company, User, CategoryPerson};
use App\Services\CompanyBranches;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Hash};
use Illuminate\Support\Str;

class BranchController extends Controller
{
    private function parent(Request $request, string $slug): Company
    {
        abort_unless(CompanyBranches::canManage($request->user()), 403);
        $company = $request->user()->company;
        abort_unless($company->slug === $slug, 403);
        return $company;
    }

    public function store(Request $request, $slug)
    {
        $parent = $this->parent($request, $slug);
        $data = $request->validate([
            'name'=>'required|string|max:255|unique:companies,name',
            'branch_type'=>'required|in:manufacturing,retail',
            'city'=>'nullable|string|max:100', 'address_1'=>'nullable|string|max:255',
            'admin_name'=>'required|string|max:255', 'email'=>'required|email|max:255|unique:users,email',
            'password'=>'required|string|min:12|max:128|confirmed',
        ]);
        $branch = DB::transaction(function () use ($parent, $data) {
            Company::whereKey($parent->id)->lockForUpdate()->firstOrFail();
            if (Company::where('parent_company_id', $parent->id)->count() >= CompanyBranches::LIMIT) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'branch_limit' => CompanyBranches::LIMIT_MESSAGE,
                ]);
            }
            $branch = new Company;
            $branch->forceFill([
                'name'=>$data['name'], 'slug'=>Str::slug($data['name']).'-'.Str::lower(Str::random(10)),
                'parent_company_id'=>$parent->id, 'branch_type'=>$data['branch_type'],
                'email'=>$data['email'], 'city'=>$data['city'] ?? null, 'address_1'=>$data['address_1'] ?? null,
                'status'=>1, 'max_users'=>$parent->max_users, 'plan'=>$parent->plan,
                'plan_started_at'=>$parent->plan_started_at, 'plan_expires_at'=>$parent->plan_expires_at,
            ])->save();
            CategoryPerson::ensureCompanyDefaults((int) $branch->id);
            $admin = User::create([
                'name'=>$data['admin_name'], 'email'=>$data['email'], 'password'=>Hash::make($data['password']),
                'company_id'=>$branch->id, 'role'=>'admin', 'is_active'=>1, 'password_changed'=>true,
            ]);
            $admin->assignRole('company_admin');
            return $branch;
        });
        if ($request->is('api/*')) return response()->json(['success'=>true, 'message'=>'Branch created.', 'data'=>['id'=>$branch->id, 'name'=>$branch->name, 'slug'=>$branch->slug, 'status'=>(bool)$branch->status]], 201);
        return back()->with('success', 'Branch and branch administrator created. Share the branch login URL and credentials securely.');
    }

    public function status(Request $request, $slug, $branch)
    {
        $parent = $this->parent($request, $slug);
        $branch = Company::where('parent_company_id', $parent->id)->findOrFail($branch);
        $data = $request->validate(['status' => 'required|boolean']);
        $branch->forceFill(['status' => (bool) $data['status']])->save();
        if ($request->is('api/*')) return response()->json(['success'=>true, 'message'=>'Branch status updated.', 'data'=>['id'=>$branch->id, 'status'=>(bool)$branch->status]]);
        return back()->with('success', $branch->name.($branch->status ? ' activated.' : ' deactivated.'));
    }

    public function update(Request $request, $slug, $branch)
    {
        $parent = $this->parent($request, $slug);
        $branch = Company::where('parent_company_id', $parent->id)->findOrFail($branch);
        $data = $request->validate([
            'name'=>'required|string|max:255|unique:companies,name,'.$branch->id,
            'branch_type'=>'required|in:manufacturing,retail', 'city'=>'nullable|string|max:100',
            'address_1'=>'nullable|string|max:255', 'status'=>'required|boolean',
        ]);
        $branch->forceFill($data)->save();
        if ($request->is('api/*')) return response()->json(['success'=>true, 'message'=>'Branch updated.', 'data'=>['id'=>$branch->id, 'name'=>$branch->name, 'slug'=>$branch->slug, 'status'=>(bool)$branch->status]]);
        return back()->with('success', 'Branch updated.');
    }
}
