<?php
namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Services\VoucherSeries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VoucherSeriesController extends Controller
{
    private function company(Request $request, ?string $slug): Company
    {
        $user=$request->user();
        abort_unless($user && $user->company_id && $user->hasRole('company_admin'),403);
        return Company::whereKey($user->company_id)->when($slug,fn($q)=>$q->where('slug',$slug))->firstOrFail();
    }

    public function show(Request $request)
    {
        return response()->json(['success'=>true,'data'=>VoucherSeries::configuration($this->company($request,null))]);
    }

    public function update(Request $request, ?string $slug=null)
    {
        $company=$this->company($request,$slug);
        $request->merge(['prefix'=>strtoupper(trim((string)$request->input('prefix','')))]);
        $data=$request->validate(['prefix'=>['nullable','string','max:20','regex:/^[A-Z0-9]+$/']]);
        DB::transaction(function() use($company,$data) {
            $locked=Company::whereKey($company->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill(['voucher_prefix'=>($data['prefix'] ?? '') ?: null])->save();
        });
        if ($slug!==null) return redirect()->route('company.profile.show',$slug)->with('voucher_success','Voucher prefix saved. Existing voucher numbers are unchanged.');
        return response()->json(['success'=>true,'data'=>VoucherSeries::configuration($company->fresh())]);
    }
}
