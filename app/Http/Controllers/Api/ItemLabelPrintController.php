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
        return $this->renderPdf($request, 'download');
    }

    public function renderPdf(Request $request, string $action)
    {
        $request = clone $request;
        $request->merge(['label_format' => \App\Services\LabelPrintFormat::resolve($request->input('label_format', 'compact'))]);
        $companies = CompanyBranches::apiManagementSelection($request);
        $validated = $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'required|integer|distinct',
            'label_format' => 'sometimes|in:compact,double_barcode,double_barcode_price,double_details',
            'start_position' => 'sometimes|integer|min:1|max:22',
        ]);
        $count = ItemSet::whereIn('company_id', $companies->keys())->whereIn('id', $validated['ids'])
            ->where('is_final', 1)->whereNotNull('qr_code')->count();
        if ($count !== count($validated['ids'])) throw ValidationException::withMessages(['ids'=>'Select printable labels belonging to the selected companies.']);
        $printRequest = clone $request;
        $printRequest->merge(['management_branches'=>$companies->keys()->all(), 'print_action'=>$action]);
        return app(\App\Http\Controllers\Company\ItemSetController::class)->printPdf($printRequest, $companies->first()->slug);
    }
}
