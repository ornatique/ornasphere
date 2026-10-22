<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Services\GstinLookup;
use App\Services\WorkerPersonService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Symfony\Component\HttpKernel\Exception\HttpException;

class GstinApiController extends Controller
{
    private function authorize(Request $request, string $permission): void
    {
        $user = $request->user();
        abort_unless($user && $user->company_id, 403);
        $permissions = $permission === 'person-create' ? ['person-create','customer-create'] : [$permission];
        abort_unless($user->hasRole('company_admin') || $user->hasAnyPermission($permissions), 403);
    }

    public function lookup(Request $request)
    {
        $this->authorize($request, 'gst-search-view');
        $input = $request->validate(['gstin'=>'required|string|max:15']);
        try { $business = app(GstinLookup::class)->lookup($input['gstin']); }
        catch (HttpException $e) { return response()->json(['success'=>false,'message'=>$e->getMessage()], $e->getStatusCode()); }
        $companyId = (int) $request->user()->company_id;
        $category = WorkerPersonService::categoryPeopleForCompany($companyId)
            ->first(fn ($row) => strtolower(trim($row->category_name)) === 'customer');
        $prefill = Arr::only($business, ['name','gst_no','pan_no','address','city','area','landmark','pincode']);
        $prefill['category_person_id'] = $category?->id;
        $prefill['is_active'] = 1;
        $prefill['remarks'] = collect(['Legal name'=>'legal_name','GST status'=>'status','State'=>'state',
            'Entity type'=>'entity_type','Nature of business'=>'nature_of_business','Department'=>'department',
            'Registration type'=>'registration_type','Registration date'=>'registration_date'])
            ->map(fn ($key,$label) => !empty($business[$key]) ? $label.': '.$business[$key] : null)->filter()->implode("\n");
        $existing = Customer::where('company_id',$companyId)->where('gst_no',$business['gst_no'])->exists();
        $canCreate = $request->user()->hasRole('company_admin') || $request->user()->hasAnyPermission(['person-create','customer-create']);
        return response()->json(['success'=>true,'data'=>[
            'business'=>$business, 'customer_form'=>$prefill, 'category_name'=>$category?->category_name,
            'existing_customer'=>$existing, 'can_add_customer'=>$canCreate && !$existing && $category !== null,
            'save_endpoint'=>url('/api/gst-search/customers'),
        ]]);
    }

    public function storeCustomer(Request $request)
    {
        $this->authorize($request, 'person-create');
        $request->merge(['gst_no'=>strtoupper(trim((string) $request->input('gst_no')))]);
        $request->validate(['gst_no'=>'required|string|regex:/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z]Z[0-9A-Z]$/']);
        $companyId = (int) $request->user()->company_id;
        if (Customer::where('company_id',$companyId)->where('gst_no',$request->input('gst_no'))->exists()) {
            return response()->json(['success'=>false,'message'=>'This GSTIN already exists in your company.'],409);
        }
        $category = WorkerPersonService::categoryPeopleForCompany($companyId)
            ->first(fn ($row) => strtolower(trim($row->category_name)) === 'customer');
        abort_unless($category,422,'Customer category is unavailable.');
        $request->merge(['category_person_id'=>$category->id]);
        return app(CustomerApiController::class)->store($request);
    }
}
