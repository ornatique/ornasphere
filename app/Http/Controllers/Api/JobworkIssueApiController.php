<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobworkIssue;
use App\Models\OtherCharge;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobworkIssueApiController extends Controller
{
    public function otherCharges(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $itemId = (int) $request->input('item_id', 0);

        $query = OtherCharge::query()
            ->where('company_id', $companyId)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id');

        if ($itemId > 0) {
            $query->where(function ($q) use ($itemId) {
                $q->whereNull('item_id')
                    ->orWhere('item_id', 0)
                    ->orWhere('item_id', $itemId);
            });
        }

        $rows = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->other_charge,
                'code' => $row->code,
                'default_amount' => (float) ($row->default_amount ?? 0),
                'default_weight' => (float) ($row->default_weight ?? 0),
                'quantity_pcs' => (float) ($row->quantity_pcs ?? 1),
                'weight_formula' => $row->weight_formula,
                'weight_percent' => (float) ($row->weight_percent ?? 0),
                'other_amt_formula' => $row->other_amt_formula,
                'is_default' => (bool) $row->is_default,
                'is_selected' => (bool) $row->is_selected,
                'item_id' => $row->item_id,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = $this->baseQuery($request, $companyId)
            ->latest('jobwork_date')
            ->get();

        $data = $rows->map(fn($row) => $this->withActions($row))->values();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->baseQuery($request, $companyId)
            ->latest('jobwork_date')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Voucher Date', 'Jobworker', 'Production Step', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Total Amt', 'Modified', 'Created']);

            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->voucher_no,
                    $this->excelText(optional($r->jobwork_date)?->format('d-m-Y')),
                    $r->jobWorker?->name ?? '-',
                    $r->productionStep?->name ?? '-',
                    number_format((float) ($r->gross_wt_sum ?? 0), 3, '.', ''),
                    number_format((float) ($r->net_wt_sum ?? 0), 3, '.', ''),
                    number_format((float) ($r->fine_wt_sum ?? 0), 3, '.', ''),
                    number_format((float) ($r->total_amt_sum ?? 0), 2, '.', ''),
                    $this->excelText(optional($r->updated_at)?->format('d-m-Y h:i A')),
                    $this->excelText(optional($r->created_at)?->format('d-m-Y h:i A')),
                ]);
            }
            fclose($out);
        }, 'jobwork_issue_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->findOrFail($companyId);
        $rows = $this->baseQuery($request, $companyId)
            ->latest('jobwork_date')
            ->get();

        return Pdf::loadView('company.jobwork_issue.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_issue_report.pdf');
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $row = JobworkIssue::where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'createdByUser:id,name', 'updatedByUser:id,name'])
            ->with('items.item:id,item_name')
            ->with('items.otherCharge:id,other_charge')
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->find($id);

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->withActions($row)]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $this->sanitizeOtherChargeSelections($request, $companyId);
        $validated = $this->validatePayload($request, $companyId);

        $row = DB::transaction(function () use ($validated, $companyId, $request) {
            $issue = JobworkIssue::create([
                'company_id' => $companyId,
                'voucher_no' => $this->generateVoucherNo($companyId, $validated['jobwork_date']),
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => $request->user()->id,
                'updated_by' => $request->user()->id,
                'modified_count' => 0,
            ]);

            foreach ($validated['items'] as $item) {
                $issue->items()->create([
                    'item_id' => $item['item_id'],
                    'other_charge_id' => $item['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($item['gross_wt'] ?? 0),
                    'other_wt' => (float) ($item['other_wt'] ?? 0),
                    'other_amt' => (float) ($item['other_amt'] ?? 0),
                    'purity' => (float) ($item['purity'] ?? 0),
                    'net_purity' => (float) ($item['net_purity'] ?? 0),
                    'net_wt' => (float) ($item['net_wt'] ?? 0),
                    'fine_wt' => (float) ($item['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($item['qty_pcs'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                    'total_amt' => (float) ($item['total_amt'] ?? 0),
                ]);
            }

            return $issue->load('items.item:id,item_name', 'jobWorker:id,name', 'productionStep:id,name');
        });

        return response()->json(['success' => true, 'message' => 'Jobwork Issue created successfully.', 'data' => $this->withActions($row)], 200);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = JobworkIssue::where('company_id', $companyId)->find($id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        $this->sanitizeOtherChargeSelections($request, $companyId);
        $validated = $this->validatePayload($request, $companyId, (int) $issue->id);

        $row = DB::transaction(function () use ($issue, $validated, $request) {
            $issue->update([
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => $request->user()->id,
                'modified_count' => ((int) $issue->modified_count) + 1,
            ]);

            $issue->items()->delete();
            foreach ($validated['items'] as $item) {
                $issue->items()->create([
                    'item_id' => $item['item_id'],
                    'other_charge_id' => $item['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($item['gross_wt'] ?? 0),
                    'other_wt' => (float) ($item['other_wt'] ?? 0),
                    'other_amt' => (float) ($item['other_amt'] ?? 0),
                    'purity' => (float) ($item['purity'] ?? 0),
                    'net_purity' => (float) ($item['net_purity'] ?? 0),
                    'net_wt' => (float) ($item['net_wt'] ?? 0),
                    'fine_wt' => (float) ($item['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($item['qty_pcs'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                    'total_amt' => (float) ($item['total_amt'] ?? 0),
                ]);
            }

            return $issue->load('items.item:id,item_name', 'jobWorker:id,name', 'productionStep:id,name');
        });

        return response()->json(['success' => true, 'message' => 'Jobwork Issue updated successfully.', 'data' => $this->withActions($row)]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = JobworkIssue::where('company_id', $companyId)->find($id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Issue not found.'], 404);
        }

        $issue->delete();
        return response()->json(['success' => true, 'message' => 'Jobwork Issue deleted successfully.']);
    }

    public function exportSingleExcel(Request $request, $id): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $row = JobworkIssue::query()
            ->where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'items.item:id,item_name', 'items.otherCharge:id,other_charge'])
            ->findOrFail($id);

        return response()->streamDownload(function () use ($row) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Voucher Date', 'Jobworker', 'Production Step', 'Item', 'Other Charge', 'Gross Wt', 'Other Wt', 'Net Wt', 'Fine Wt', 'Qty', 'Purity', 'Net Purity', 'Total Amt', 'Remarks']);
            foreach ($row->items as $i) {
                fputcsv($out, [
                    $row->voucher_no,
                    $this->excelText(optional($row->jobwork_date)?->format('d-m-Y')),
                    $row->jobWorker?->name ?? '-',
                    $row->productionStep?->name ?? '-',
                    $i->item?->item_name ?? '-',
                    $i->otherCharge?->other_charge ?? '-',
                    number_format((float) ($i->gross_wt ?? 0), 3, '.', ''),
                    number_format((float) ($i->other_wt ?? 0), 3, '.', ''),
                    number_format((float) ($i->net_wt ?? 0), 3, '.', ''),
                    number_format((float) ($i->fine_wt ?? 0), 3, '.', ''),
                    (int) ($i->qty_pcs ?? 0),
                    number_format((float) ($i->purity ?? 0), 3, '.', ''),
                    number_format((float) ($i->net_purity ?? 0), 3, '.', ''),
                    number_format((float) ($i->total_amt ?? 0), 2, '.', ''),
                    $i->remarks ?? '',
                ]);
            }
            fclose($out);
        }, 'jobwork_issue_' . $row->voucher_no . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportSinglePdf(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->findOrFail($companyId);
        $row = JobworkIssue::query()
            ->where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'items.item:id,item_name', 'items.otherCharge:id,other_charge'])
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->findOrFail($id);

        return Pdf::loadView('company.jobwork_issue.pdf.show', compact('company', 'row'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_issue_' . $row->voucher_no . '.pdf');
    }

    private function validatePayload(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'jobwork_date' => ['required', 'date'],
            'job_worker_id' => [
                'required',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'production_step_id' => [
                'required',
                'integer',
                Rule::exists('production_steps', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => [
                'required',
                'integer',
                Rule::exists('items', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.other_charge_id' => [
                'nullable',
                function ($attribute, $value, $fail) use ($companyId) {
                    if ($value === null || $value === '' || $value === 'null' || $value === 'undefined') {
                        return;
                    }

                    if (!is_numeric($value) || (int) $value <= 0) {
                        $fail("The selected {$attribute} is invalid.");
                        return;
                    }

                    $exists = OtherCharge::query()
                        ->where('company_id', $companyId)
                        ->where('id', (int) $value)
                        ->exists();

                    if (!$exists) {
                        $fail("The selected {$attribute} is invalid.");
                    }
                },
            ],
            'items.*.gross_wt' => ['nullable', 'numeric'],
            'items.*.other_wt' => ['nullable', 'numeric'],
            'items.*.other_amt' => ['nullable', 'numeric'],
            'items.*.purity' => ['nullable', 'numeric'],
            'items.*.net_purity' => ['nullable', 'numeric'],
            'items.*.net_wt' => ['nullable', 'numeric'],
            'items.*.fine_wt' => ['nullable', 'numeric'],
            'items.*.qty_pcs' => ['nullable', 'integer'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.total_amt' => ['nullable', 'numeric'],
        ]);
    }

    private function sanitizeOtherChargeSelections(Request $request, int $companyId): void
    {
        $items = $request->input('items', []);
        if (!is_array($items) || empty($items)) {
            return;
        }

        $chargeIds = collect($items)
            ->map(fn($row) => (int) ($row['other_charge_id'] ?? 0))
            ->filter(fn($id) => $id > 0)
            ->unique()
            ->values();

        if ($chargeIds->isEmpty()) {
            return;
        }

        $charges = OtherCharge::query()
            ->where('company_id', $companyId)
            ->whereIn('id', $chargeIds->all())
            ->get(['id', 'item_id'])
            ->keyBy('id');

        foreach ($items as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }

            $itemId = (int) ($row['item_id'] ?? 0);
            $otherChargeId = (int) ($row['other_charge_id'] ?? 0);

            if ($otherChargeId <= 0) {
                $items[$idx]['other_charge_id'] = null;
                continue;
            }

            $charge = $charges->get($otherChargeId);
            if (!$charge) {
                $items[$idx]['other_charge_id'] = null;
                continue;
            }

            $chargeItemId = (int) ($charge->item_id ?? 0);
            $allowedForItem = ($chargeItemId === 0 || $chargeItemId === $itemId);
            if (!$allowedForItem) {
                // Item changed; clear stale old charge selection instead of failing validation.
                $items[$idx]['other_charge_id'] = null;
            }
        }

        $request->merge(['items' => $items]);
    }

    private function baseQuery(Request $request, int $companyId)
    {
        return JobworkIssue::query()
            ->where('company_id', $companyId)
            ->with(['jobWorker:id,name', 'productionStep:id,name'])
            ->with('items.item:id,item_name')
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('jobwork_date', '>=', $request->from_date))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('jobwork_date', '<=', $request->to_date));
    }

    private function excelText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return "'" . $value;
    }

    private function generateVoucherNo(int $companyId, string $date): string
    {
        $yearShort = Carbon::parse($date)->format('y');
        $prefix = 'JI' . $yearShort . '-';

        $last = JobworkIssue::where('company_id', $companyId)
            ->where('voucher_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('voucher_no');

        $next = 1;
        if ($last && str_contains($last, '-')) {
            $parts = explode('-', $last);
            $lastNo = (int) end($parts);
            $next = $lastNo + 1;
        }

        return $prefix . $next;
    }

    private function withActions(JobworkIssue $row): array
    {
        $data = $row->toArray();
        $id = (int) $row->id;
        $data['jobwork_date_view'] = optional($row->jobwork_date)?->format('d-m-Y');
        $data['modified_at_view'] = optional($row->updated_at)?->format('d-m-Y h:i A');
        $data['created_at_view'] = optional($row->created_at)?->format('d-m-Y h:i A');
        $data['created_by_name'] = $row->createdByUser?->name;
        $data['updated_by_name'] = $row->updatedByUser?->name;

        $data['actions'] = [
            'view' => [
                'method' => 'GET',
                'url' => url("/api/jobwork-issues/{$id}"),
            ],
            'edit' => [
                // Use this URL to submit updated payload.
                'method' => 'PUT',
                'url' => url("/api/jobwork-issues/{$id}"),
                // Use view URL to load existing data before edit.
                'load_url' => url("/api/jobwork-issues/{$id}"),
            ],
            'delete' => [
                'method' => 'DELETE',
                'url' => url("/api/jobwork-issues/{$id}"),
            ],
            // Backward-compatible aliases used in this project.
            'legacy_edit' => [
                'method' => 'POST',
                'url' => url("/api/update-jobwork-issues/{$id}"),
            ],
            'legacy_delete' => [
                'method' => 'DELETE',
                'url' => url("/api/delete-jobwork-issues/{$id}"),
            ],
        ];

        return $data;
    }
}
