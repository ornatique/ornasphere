<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemSet;
use App\Services\CompanyBranches;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class ItemLabelPrintController extends Controller
{
    public function pdf(Request $request)
    {
        $companies = CompanyBranches::apiManagementSelection($request);
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'required|integer|distinct',
            'label_format' => 'sometimes|in:compact,double_barcode',
            'start_position' => 'sometimes|integer|min:1|max:22',
        ]);
        $count = ItemSet::whereIn('company_id', $companies->keys())->whereIn('id', $validated['ids'])
            ->where('is_final', 1)->whereNotNull('qr_code')->count();
        if ($count !== count($validated['ids'])) throw ValidationException::withMessages(['ids'=>'Select printable labels belonging to the selected companies.']);
        $printRequest = clone $request;
        $printRequest->merge(['management_branches'=>$companies->keys()->all(), 'print_action'=>'download']);
        return app(\App\Http\Controllers\Company\ItemSetController::class)->printPdf($printRequest, $companies->first()->slug);
    }
}
