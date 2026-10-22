<?php
namespace App\Services;

use Illuminate\Http\Request;

class CastingApiBranches
{
    /** Run the existing stage query per authorized company to preserve its calculations. */
    public static function listing(Request $request, callable $listForCompany, string $title)
    {
        $companies = CompanyBranches::apiManagementSelection($request);
        if ($request->filled('export')) $request->validate(['export'=>'required|in:pdf,excel']);
        $rows = collect();
        foreach ($companies as $company) {
            $response = $listForCompany((int)$company->id);
            if ($response->getStatusCode() !== 200) return $response;
            foreach ($response->getData(true)['data'] as $row) {
                $rows->push(array_merge($row, ['company_id'=>(int)$company->id, 'company_name'=>$company->name]));
            }
        }
        if ($request->filled('export')) {
            $columns = ['company_name'=>'Company / Branch'];
            foreach ($rows as $row) {
                foreach ($row as $key=>$value) {
                    if ($key === 'company_name' || $key === 'id' || str_ends_with($key,'_id') || str_ends_with($key,'_view')
                        || str_starts_with($key,'can_') || is_array($value) || $key === 'process_datetime') continue;
                    $columns[$key] = ucwords(str_replace('_',' ',$key));
                }
            }
            return CastingListExport::download($request, $companies, $title, $columns, $rows);
        }
        return response()->json(['success'=>true, 'data'=>$rows->values(), 'selected_company_ids'=>$companies->keys()->values()]);
    }
}
