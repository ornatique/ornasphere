<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\CompanyPlanService;
use Illuminate\Http\Request;

class CompanyProfileController extends Controller
{
    public function openingBalances(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $user = $request->user();
        abort_unless($user && (int)$user->company_id === (int)$company->id && $user->hasRole('company_admin') && !$company->parent_company_id, 403);
        $data = $request->validate([
            'opening_balance_date'=>'required|date',
            'opening_cash'=>'required|numeric|min:0|max:9999999999999999.99',
            'opening_bank'=>'required|numeric|min:0|max:9999999999999999.99',
            'opening_gold_fine'=>'required|numeric|min:0|max:999999999999999.999',
            'opening_silver_fine'=>'required|numeric|min:0|max:999999999999999.999',
        ]);
        $company->forceFill($data)->save();
        return redirect()->route('company.profile.show', $slug)->with('opening_success', 'Company opening balances saved.');
    }
    public function show(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $user = $request->user();

        abort_if((int) $user->company_id !== (int) $company->id, 403);

        $companyPlanStatus = CompanyPlanService::status($company);
        $voucherConfiguration = $user->hasRole('company_admin')
            ? \App\Services\VoucherSeries::configuration($company) : null;

        $canManageBranches = \App\Services\CompanyBranches::canManage($user);
        $branches = $canManageBranches ? Company::where('parent_company_id', $company->id)->orderBy('name')->get() : collect();
        return view('company.profile.show', compact('company', 'user', 'companyPlanStatus', 'voucherConfiguration', 'canManageBranches', 'branches'));
    }
}
