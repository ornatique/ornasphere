<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Models\JobWorker;
use App\Models\JobworkIssue;
use App\Models\OtherCharge;
use App\Models\ProductionStep;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class JobworkIssueController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = $this->baseQuery($company, $request);

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('jobworker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('production_step_name', fn($row) => $row->productionStep?->name ?? '-')
                ->addColumn('jobwork_date_view', fn($row) => optional($row->jobwork_date)->format('d-m-Y') ?? '-')
                ->addColumn('gross_wt_sum', fn($row) => number_format((float) ($row->gross_wt_sum ?? 0), 3, '.', ''))
                ->addColumn('net_wt_sum', fn($row) => number_format((float) ($row->net_wt_sum ?? 0), 3, '.', ''))
                ->addColumn('fine_wt_sum', fn($row) => number_format((float) ($row->fine_wt_sum ?? 0), 3, '.', ''))
                ->addColumn('total_amt_sum', fn($row) => number_format((float) ($row->total_amt_sum ?? 0), 2, '.', ''))
                ->addColumn('user_name', fn($row) => $row->createdByUser?->name ?? '-')
                ->addColumn('modified_at_view', fn($row) => optional($row->updated_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $editUrl = route('company.jobwork-issue.edit', [$company->slug, $id]);
                    $viewUrl = route('company.jobwork-issue.show', [$company->slug, $id]);
                    $deleteUrl = route('company.jobwork-issue.destroy', [$company->slug, $id]);

                    return '<div class="d-flex gap-1">
                                <a href="' . $viewUrl . '" class="btn btn-sm btn-info">View</a>
                                <a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                                <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($deleteUrl) . '">Delete</button>
                            </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.jobwork_issue.index', compact('company'));
    }

    public function show($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $row = JobworkIssue::query()
            ->where('company_id', $company->id)
            ->with([
                'jobWorker:id,name',
                'productionStep:id,name',
                'createdByUser:id,name',
                'items.item:id,item_name',
                'items.otherCharge:id,other_charge',
            ])
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->findOrFail($id);

        return view('company.jobwork_issue.show', compact('company', 'row'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $jobWorkers = JobWorker::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $productionSteps = ProductionStep::where('company_id', $company->id)->where('status', true)->orderBy('name')->get(['id', 'name']);
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'outward_purity', 'inward_purity']);
        $otherCharges = OtherCharge::where('company_id', $company->id)->orderBy('other_charge')->get([
            'id',
            'other_charge',
            'default_amount',
            'default_weight',
            'quantity_pcs',
            'weight_formula',
            'weight_percent',
            'other_amt_formula',
            'wt_operation',
            'is_default',
            'is_selected',
        ]);

        return view('company.jobwork_issue.create', compact('company', 'jobWorkers', 'productionSteps', 'items', 'otherCharges'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $validated = $this->validatePayload($request, $company->id);

        DB::transaction(function () use ($validated, $company) {
            $issue = JobworkIssue::create([
                'company_id' => $company->id,
                'voucher_no' => $this->generateVoucherNo($company->id, $validated['jobwork_date']),
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'modified_count' => 0,
            ]);

            foreach ($validated['items'] as $row) {
                $issue->items()->create([
                    'item_id' => $row['item_id'],
                    'other_charge_id' => $row['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($row['gross_wt'] ?? 0),
                    'other_wt' => (float) ($row['other_wt'] ?? 0),
                    'other_amt' => (float) ($row['other_amt'] ?? 0),
                    'purity' => (float) ($row['purity'] ?? 0),
                    'net_purity' => (float) ($row['net_purity'] ?? 0),
                    'net_wt' => (float) ($row['net_wt'] ?? 0),
                    'fine_wt' => (float) ($row['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($row['qty_pcs'] ?? 0),
                    'remarks' => $row['remarks'] ?? null,
                    'total_amt' => (float) ($row['total_amt'] ?? 0),
                ]);
            }
        });

        return redirect()
            ->route('company.jobwork-issue.index', $company->slug)
            ->with('success', 'Jobwork Issue created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = JobworkIssue::where('company_id', $company->id)->with('items')->findOrFail($id);
        $jobWorkers = JobWorker::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $productionSteps = ProductionStep::where('company_id', $company->id)->where('status', true)->orderBy('name')->get(['id', 'name']);
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'outward_purity', 'inward_purity']);
        $otherCharges = OtherCharge::where('company_id', $company->id)->orderBy('other_charge')->get([
            'id',
            'other_charge',
            'default_amount',
            'default_weight',
            'quantity_pcs',
            'weight_formula',
            'weight_percent',
            'other_amt_formula',
            'wt_operation',
            'is_default',
            'is_selected',
        ]);

        return view('company.jobwork_issue.create', compact('company', 'data', 'jobWorkers', 'productionSteps', 'items', 'otherCharges'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $issue = JobworkIssue::where('company_id', $company->id)->findOrFail($id);
        $validated = $this->validatePayload($request, $company->id, (int) $issue->id);

        DB::transaction(function () use ($issue, $validated) {
            $issue->update([
                'jobwork_date' => $validated['jobwork_date'],
                'job_worker_id' => $validated['job_worker_id'],
                'production_step_id' => $validated['production_step_id'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
                'modified_count' => ((int) $issue->modified_count) + 1,
            ]);

            $issue->items()->delete();

            foreach ($validated['items'] as $row) {
                $issue->items()->create([
                    'item_id' => $row['item_id'],
                    'other_charge_id' => $row['other_charge_id'] ?? null,
                    'gross_wt' => (float) ($row['gross_wt'] ?? 0),
                    'other_wt' => (float) ($row['other_wt'] ?? 0),
                    'other_amt' => (float) ($row['other_amt'] ?? 0),
                    'purity' => (float) ($row['purity'] ?? 0),
                    'net_purity' => (float) ($row['net_purity'] ?? 0),
                    'net_wt' => (float) ($row['net_wt'] ?? 0),
                    'fine_wt' => (float) ($row['fine_wt'] ?? 0),
                    'qty_pcs' => (int) ($row['qty_pcs'] ?? 0),
                    'remarks' => $row['remarks'] ?? null,
                    'total_amt' => (float) ($row['total_amt'] ?? 0),
                ]);
            }
        });

        return redirect()
            ->route('company.jobwork-issue.index', $company->slug)
            ->with('success', 'Jobwork Issue updated successfully');
    }

    public function destroy(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $issue = JobworkIssue::where('company_id', $company->id)->findOrFail($id);
        $issue->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Jobwork Issue deleted successfully',
            ]);
        }

        return redirect()
            ->route('company.jobwork-issue.index', $company->slug)
            ->with('success', 'Jobwork Issue deleted successfully');
    }

    public function exportExcel(Request $request, $slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->baseQuery($company, $request)->latest('jobwork_date')->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Voucher No', 'Voucher Date', 'Jobworker', 'Production Step', 'Gross Wt', 'Net Wt', 'Fine Wt', 'Total Amt', 'Created By', 'Modified', 'Created']);

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
                    $r->createdByUser?->name ?? '-',
                    $this->excelText(optional($r->updated_at)?->format('d-m-Y h:i A')),
                    $this->excelText(optional($r->created_at)?->format('d-m-Y h:i A')),
                ]);
            }
            fclose($out);
        }, 'jobwork_issue_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->baseQuery($company, $request)->latest('jobwork_date')->get();

        return Pdf::loadView('company.jobwork_issue.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_issue_report.pdf');
    }

    public function exportSingleExcel($slug, $encryptedId): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $row = JobworkIssue::query()
            ->where('company_id', $company->id)
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'createdByUser:id,name', 'items.item:id,item_name', 'items.otherCharge:id,other_charge'])
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

    public function exportSinglePdf($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $row = JobworkIssue::query()
            ->where('company_id', $company->id)
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'createdByUser:id,name', 'items.item:id,item_name', 'items.otherCharge:id,other_charge'])
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->findOrFail($id);

        return Pdf::loadView('company.jobwork_issue.pdf.show', compact('company', 'row'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_issue_' . $row->voucher_no . '.pdf');
    }

    private function baseQuery(Company $company, Request $request)
    {
        return JobworkIssue::query()
            ->where('company_id', $company->id)
            ->select('jobwork_issues.*')
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'createdByUser:id,name', 'updatedByUser:id,name'])
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

        // Force Excel to keep the exact display value as text.
        return "'" . $value;
    }

    private function validatePayload(Request $request, int $companyId, ?int $issueId = null): array
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
                'integer',
                Rule::exists('other_charges', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
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
}
