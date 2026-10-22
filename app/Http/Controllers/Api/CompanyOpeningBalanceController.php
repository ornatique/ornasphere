<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\CompanyProfileController;
use App\Models\Company;
use Illuminate\Http\Request;

class CompanyOpeningBalanceController extends Controller
{
    private function company(Request $request): Company
    {
        $user = $request->user();
        abort_unless($user && $user->hasRole('company_admin'), 403);
        $company = Company::findOrFail($user->company_id);
        abort_if($company->parent_company_id, 403, 'Opening balances are available only for the main company.');
        return $company;
    }

    public function show(Request $request)
    {
        return response()->json(['success'=>true, 'data'=>$this->payload($this->company($request))]);
    }

    public function update(Request $request)
    {
        $company = $this->company($request);
        app(CompanyProfileController::class)->openingBalances($request, $company->slug);
        return response()->json(['success'=>true, 'message'=>'Company opening balances saved.', 'data'=>$this->payload($company->fresh())]);
    }

    private function payload(Company $company): array
    {
        $data = ['company_id'=>$company->id, 'opening_balance_date'=>$company->opening_balance_date,
            'configured'=>$company->opening_balance_date !== null, 'currency'=>'INR', 'metal_unit'=>'fine_g'];
        foreach (['opening_cash'=>2,'opening_bank'=>2,'opening_gold_fine'=>3,'opening_silver_fine'=>3] as $field=>$precision) {
            $data[$field] = number_format((float)($company->$field ?? 0), $precision, '.', '');
        }
        return $data;
    }
}
