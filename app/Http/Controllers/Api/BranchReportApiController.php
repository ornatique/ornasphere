<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Company\ReportController;
use App\Services\ReportBranches;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

/** Reuses the web report calculations and branch scope for the company admin app. */
class BranchReportApiController extends Controller
{
    private function authorizeOwnRegister(Request $request, string $permission = 'report-approval-outstanding-view'): void
    {
        $user = $request->user();
        abort_unless($user && $user->company, 403);
        abort_unless($user->hasRole('company_admin') || $user->hasAnyPermission([$permission]), 403);
        $slug = $user->company->slug;
        abort_if($request->has('report_branches') && $request->input('report_branches') !== $slug, 403, 'Use this endpoint for your own company only.');
        $request->merge(['report_branches' => $slug]);
    }

    public function ownRegister(Request $request)
    {
        $this->authorizeOwnRegister($request);
        return $this->renderReport($request, 'approval-return-register');
    }

    public function ownSummary(Request $request)
    {
        $this->authorizeOwnRegister($request, 'report-approval-summary-view');
        return $this->renderReport($request, 'approval-summary');
    }

    public function ownRegisterDetails(Request $request, string $type, int $id)
    {
        $this->authorizeOwnRegister($request);
        return $this->renderDetails($request, $type, $id);
    }

    public function details(Request $request, string $type, int $id)
    {
        abort_unless($request->user()->hasRole('company_admin'), 403);
        return $this->renderDetails($request, $type, $id);
    }

    private function renderDetails(Request $request, string $type, int $id)
    {
        $result = app(ReportController::class)->approvalRegisterDetails($request, $request->user()->company->slug, $type, $id)->getData();
        return response()->json(['success'=>true, 'data'=>[
            'number'=>$result['number'], 'date'=>$result['date'], 'type'=>$result['type'],
            'lines'=>$result['lines']->values(),
        ]]);
    }

    private const REPORTS = [
        'sales-summary'=>'salesSummary', 'purchase-receiver-summary'=>'purchaseReceiverSummary',
        'stock-position'=>'stockPosition', 'worker-loss'=>'workerLoss',
        'approval-outstanding'=>'approvalOutstanding', 'outstanding-amount'=>'outstandingAmount',
        'barcode-history'=>'barcodeHistory', 'approval-summary'=>'approvalSummary',
        'approval-return-register'=>'approvalReturnRegister',
    ];

    public function show(Request $request, string $report)
    {
        abort_unless($request->user()->hasRole('company_admin'), 403);
        return $this->renderReport($request, $report);
    }

    private function renderReport(Request $request, string $report)
    {
        abort_unless(isset(self::REPORTS[$report]), 404);
        $company = $request->user()->company;
        ReportBranches::ids($company, $request);
        $request->validate(['export'=>in_array($report, ['approval-summary', 'barcode-history'], true) ? 'nullable|in:pdf,csv,xlsx,excel' : 'nullable|in:pdf,csv', 'start'=>'nullable|integer|min:0', 'length'=>'nullable|integer|min:1|max:100']);
        $method = self::REPORTS[$report];
        $export = $request->input('export');
        $isApproval = in_array($report, ['approval-summary','approval-return-register'], true);
        if ($export && !$isApproval) $method .= $export === 'pdf' ? 'Pdf' : 'Excel';
        if (!$export && !$isApproval) {
            $request->headers->set('X-Requested-With', 'XMLHttpRequest');
            $request->merge(['length'=>(int)$request->input('length', 25), 'start'=>(int)$request->input('start', 0)]);
        }
        $result = app(ReportController::class)->$method($request, $company->slug);
        if ($result instanceof View) {
            $data = array_intersect_key($result->getData(), array_flip(['rows','metrics','totals','ledger']));
            if ($report === 'approval-summary') {
                $data['customers'] = $result->getData()['customers']->values();
                $data['selected_customer_id'] = $request->filled('customer_id') ? (int) $request->input('customer_id') : null;
            }
            if ($report === 'approval-return-register') {
                $viewData = $result->getData();
                $pending = $viewData['pendingItems']->values();
                $counts = $pending->groupBy('voucher_id')->map->count();
                $data['rows']->getCollection()->transform(function ($row) use ($counts) {
                    $row->pending_qty = $row->entry_type === 'Approval' ? (int) ($counts[$row->voucher_id] ?? 0) : null;
                    $row->branch_name = ReportBranches::name($row->company_id);
                    return $row;
                });
                $data['pending_items'] = $pending;
                $data['pending_count'] = $pending->count();
                $data['pending_scope'] = 'Current pending items for approvals matching customer, voucher, search and issue-date filters; independent of entry_type.';
                $data['customers'] = $viewData['customers']->map(fn ($customer) => [
                    'id' => $customer->id,
                    'filter_value' => \Illuminate\Support\Facades\Crypt::encryptString((string) $customer->id),
                    'name' => $customer->name, 'company_id' => $customer->company_id,
                    'branch_name' => ReportBranches::name($customer->company_id),
                ])->values();
                $data['selected_customer_id'] = $viewData['selectedCustomerId'];
                $data['entry_types'] = ['Approval', 'Approval Return', 'Sale from Approval'];
                $data['columns'] = ['entry_date'=>'Date','entry_type'=>'Type','voucher_no'=>'Voucher',
                    'approval_no'=>'Approval Voucher','customer_name'=>'Customer','branch_name'=>'Branch',
                    ...$data['metrics'], 'pending_qty'=>'Pending Qty'];
            }
            return response()->json(['success'=>true, 'heading'=>ReportBranches::heading($company), 'data'=>$data]);
        }
        return $result;
    }
}
