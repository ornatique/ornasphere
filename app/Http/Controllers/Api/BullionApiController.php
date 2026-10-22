<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\BullionController;
use App\Models\{Customer, CustomerAdvanceLedger};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;

class BullionApiController extends Controller
{
    private function company(Request $request, string $action = 'view')
    {
        $user = $request->user();
        abort_unless($user && $user->company && ($user->hasRole('company_admin') || $user->can('bullion-'.$action)), 403);
        $companies = \App\Services\CompanyBranches::apiManagementSelection($request);
        if ($request->filled('person_id')) {
            $person = Customer::whereIn('company_id', $companies->keys())->findOrFail($request->integer('person_id'));
            $company = $companies[$person->company_id];
        } else $company = $companies->first();
        if ($action !== 'view' && !\App\Services\BranchReadOnly::canWrite($user, $company->id)) abort(403, \App\Services\BranchReadOnly::MESSAGE);
        return $company;
    }

    private function people(int $companyId)
    {
        return Customer::where('company_id', $companyId)->whereHas('categoryPerson', fn($q) =>
            $q->where('company_id', $companyId)->whereRaw('LOWER(TRIM(category_name)) = ?', ['bullion']));
    }

    private function person(Request $request, int $companyId)
    {
        $data = $request->validate(['person_id'=>'required|integer|min:1']);
        return $this->people($companyId)->findOrFail($data['person_id']);
    }

    private function totals(int $companyId, int $personId): array
    {
        $row = CustomerAdvanceLedger::where('company_id', $companyId)->where('customer_id', $personId)
            ->selectRaw("COALESCE(SUM(cash_in-cash_out),0) as cash, COALESCE(SUM(CASE WHEN metal_type='gold' THEN metal_in-metal_out ELSE 0 END),0) as gold, COALESCE(SUM(CASE WHEN metal_type='silver' THEN metal_in-metal_out ELSE 0 END),0) as silver")->first();
        return ['cash'=>round((float)$row->cash,2),'gold'=>round((float)$row->gold,3),'silver'=>round((float)$row->silver,3)];
    }

    public function persons(Request $request)
    {
        $company = $this->company($request);
        $companies = \App\Services\CompanyBranches::apiManagementSelection($request);
        $people = $companies->flatMap(fn ($branch) => $this->people($branch->id)->orderBy('name')->get(['id','name','company_id'])->map(fn ($person) => ['id'=>$person->id,'name'=>$person->name,'company_id'=>$branch->id,'company_name'=>$branch->name,'can_manage'=>\App\Services\BranchReadOnly::canWrite($request->user(), $branch->id)]))->values();
        return response()->json(['success'=>true,'data'=>$people]);
    }

    public function balance(Request $request)
    {
        $company = $this->company($request);
        $person = $this->person($request,$company->id);
        return response()->json(['success'=>true,'data'=>['company_id'=>$company->id,'company_name'=>$company->name,'person_id'=>$person->id,'name'=>$person->name,'currency'=>'INR','metal_unit'=>'fine_g','balance'=>$this->totals($company->id,$person->id)]]);
    }

    public function entries(Request $request)
    {
        $company = $this->company($request);
        $person = $this->person($request,$company->id);
        $request->validate(['per_page'=>'nullable|integer|min:1|max:100']);
        $rows = CustomerAdvanceLedger::where('company_id',$company->id)->where('customer_id',$person->id)
            ->orderByDesc('entry_date')->orderByDesc('id')->paginate((int)$request->input('per_page',25));
        $rows->through(function ($entry) use ($company, $request) { $entry->company_name=$company->name; $entry->can_manage=\App\Services\BranchReadOnly::canWrite($request->user(), $company->id); return $entry; });
        return response()->json(['success'=>true,'data'=>$rows]);
    }

    public function preview(Request $request)
    {
        $company = $this->company($request,'create');
        $person = $this->person($request,$company->id);
        $data = $request->validate([
            'entry_type'=>'required|in:convert_to_metal,convert_to_rupees',
            'metal_type'=>'required|in:gold,silver','rate'=>'required|numeric|min:0.01',
            'amount'=>'required_if:entry_type,convert_to_metal|nullable|numeric|gt:0',
            'net_weight'=>'required_if:entry_type,convert_to_rupees|nullable|numeric|gt:0',
            'purity'=>'required_if:entry_type,convert_to_rupees|nullable|numeric|gt:0|max:100',
        ]);
        $rate = round((float)$data['rate'],2);
        $toMetal = $data['entry_type'] === 'convert_to_metal';
        $amount = $toMetal ? round((float)$data['amount'],2) : 0;
        $fine = $toMetal ? round($amount/$rate,3) : round($data['net_weight']*$data['purity']/100,3);
        if (!$toMetal) $amount = round($fine*$rate,2);
        if ($fine <= 0 || $amount <= 0) throw \Illuminate\Validation\ValidationException::withMessages(['transaction'=>'Enter a positive transaction amount or weight.']);
        $before = $this->totals($company->id,$person->id);
        $after = $before;
        $after['cash'] = round($after['cash'] + ($toMetal ? -$amount : $amount),2);
        $after[$data['metal_type']] = round($after[$data['metal_type']] + ($toMetal ? $fine : -$fine),3);
        return response()->json(['success'=>true,'data'=>['amount'=>$amount,'fine_weight'=>$fine,'rate'=>$rate,'balance_before'=>$before,'balance_after'=>$after]]);
    }

    public function store(Request $request)
    {
        $company = $this->company($request,'create');
        $person = $this->person($request,$company->id);
        $request->merge(['customer_id'=>$person->id]);
        app(BullionController::class)->store($request,$company->slug);
        return response()->json(['success'=>true,'message'=>'Bullion entry saved.','data'=>['person_id'=>$person->id,'balance'=>$this->totals($company->id,$person->id)]],201);
    }

    public function pdf(Request $request)
    {
        $company = $this->company($request);
        $person = $this->person($request,$company->id);
        $request->query->set('customer_id',Crypt::encryptString((string)$person->id));
        $request->query->set('format','pdf');
        $request->merge(['management_branches'=>[$company->id]]);
        return app(BullionController::class)->index($request,$company->slug);
    }
}
