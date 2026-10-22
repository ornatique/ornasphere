<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\BranchController;
use App\Http\Controllers\Company\CompanyDashboardController;
use App\Models\Customer;
use App\Services\{CompanyBranches, CompanyPlanService, ReportBranches};
use Illuminate\Http\Request;

class CompanyBranchApiController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $canManage = CompanyBranches::canManage($user);
        $companies = CompanyBranches::visibleCompanies($user);
        $count = $canManage ? $companies->where('parent_company_id', $user->company_id)->count() : 0;
        return response()->json(['success'=>true, 'data'=>[
            'can_manage_branches'=>$canManage, 'branch_limit'=>CompanyBranches::LIMIT,
            'branch_count'=>$count, 'can_create_branch'=>$canManage && $count < CompanyBranches::LIMIT,
            'limit_message'=>$canManage && $count >= CompanyBranches::LIMIT ? CompanyBranches::LIMIT_MESSAGE : null,
            'companies'=>$companies->map(fn ($company)=>[
                'id'=>$company->id, 'name'=>$company->name, 'slug'=>$company->slug,
                'parent_company_id'=>$company->parent_company_id, 'branch_type'=>$company->branch_type,
                'city'=>$company->city, 'address_1'=>$company->address_1,
                'status'=>(bool)$company->status, 'effective_active'=>CompanyBranches::isActive($company), 'is_main'=>!$company->parent_company_id,
                'login_url'=>route('company.login', $company->slug),
                'plan'=>CompanyPlanService::status($company),
            ])->values(),
        ]]);
    }

    public function store(Request $request) { return app(BranchController::class)->store($request, $request->user()->company->slug); }
    public function update(Request $request, $branch) { return app(BranchController::class)->update($request, $request->user()->company->slug, $branch); }
    public function status(Request $request, $branch) { return app(BranchController::class)->status($request, $request->user()->company->slug, $branch); }

    public function dashboard(Request $request)
    {
        $data = app(CompanyDashboardController::class)->index($request)->getData();
        abort_unless($data['canViewDashboardData'], 403);
        unset($data['dashboardBranches']);
        return response()->json(['success'=>true, 'data'=>$data]);
    }

    public function customers(Request $request)
    {
        abort_unless($request->user()->hasRole('company_admin'), 403);
        $ids = ReportBranches::ids($request->user()->company, $request);
        return response()->json(['success'=>true, 'data'=>Customer::whereIn('company_id', $ids)
            ->orderBy('name')->get(['id','name','company_id'])->map(fn ($customer)=>[
                'id'=>$customer->id, 'name'=>$customer->name, 'company_id'=>$customer->company_id,
                'branch_name'=>ReportBranches::name($customer->company_id),
                'register_filter_id'=>encrypt((string)$customer->id, false),
            ])]);
    }
}
