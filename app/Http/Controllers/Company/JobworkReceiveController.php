<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Models\JobworkIssue;
use App\Models\JobworkReceive;
use App\Models\OtherCharge;
use App\Services\WorkerPersonService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class JobworkReceiveController extends Controller
{
    public function index(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            return $this->receiveVoucherDataTable($this->baseQuery($company, $request), $company, false);
        }

        $jobWorkers = $this->jobWorkers($company);

        return view('company.jobwork_receive.index', compact('company', 'jobWorkers'));
    }

    public function create(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            if (!$request->filled('worker_id')) {
                return $this->receiveVoucherDataTable(JobworkIssue::query()->whereRaw('1 = 0'), $company, false);
            }

            return $this->receiveVoucherDataTable($this->baseQuery($company, $request), $company, false);
        }

        $jobWorkers = $this->jobWorkers($company);

        return view('company.jobwork_receive.create', compact('company', 'jobWorkers'));
    }

    public function show(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $row = $this->findIssue($company, (int) $id);
        $receive = $this->ensureReceive($company, $row);
        $issueItemOptions = $this->issueItemOptions($row);
        $receiveItemOptions = $this->receiveItemOptions($company, $issueItemOptions);
        $workerIssueVouchers = $this->workerIssueVouchers($company, $row);
        $otherCharges = $this->otherChargeOptions($company);

        return view('company.jobwork_receive.show', compact('company', 'row', 'receive', 'issueItemOptions', 'receiveItemOptions', 'workerIssueVouchers', 'otherCharges'));
    }

    public function update(Request $request, string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $row = $this->findIssue($company, (int) $id);
        $receive = $this->ensureReceive($company, $row);

        $validated = $request->validate([
            'receive_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($query) => $query->where('company_id', $company->id)),
            ],
            'items.*.receive_gross_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_amt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_charge_details' => ['nullable', 'string'],
            'items.*.receive_net_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_fine_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_qty_pcs' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        DB::transaction(function () use ($validated, $receive, $row) {
            $receive->update([
                'receive_date' => $validated['receive_date'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);

            $receive->items()->delete();
            $this->createReceiveItems($receive, $row, $validated['items'] ?? []);
        });

        return redirect()
            ->route('company.jobwork-receive.index', $company->slug)
            ->with('success', 'Jobwork Receive updated successfully');
    }

    public function pdf(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $row = $this->findIssue($company, (int) $id);
        $receive = $this->ensureReceive($company, $row);
        $workerIssueVouchers = $this->workerIssueVouchers($company, $row);

        return Pdf::loadView('company.jobwork_receive.pdf.show', compact('company', 'row', 'receive', 'workerIssueVouchers'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_receive_' . $row->voucher_no . '.pdf');
    }

    private function baseQuery(Company $company, Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $workerId = $request->input('worker_id');

        return JobworkIssue::query()
            ->where('company_id', $company->id)
            ->whereHas('items')
            ->with([
                'jobWorker:id,name',
                'productionStep:id,name',
                'receive' => fn($query) => $query
                    ->withCount([
                        'items as assigned_receive_count' => fn($itemQuery) => $itemQuery
                            ->where(function ($q) {
                                $q->where('receive_net_wt', '>', 0)
                                    ->orWhere('receive_qty_pcs', '>', 0);
                            }),
                    ])
                    ->withSum('items as receive_net_wt_sum', 'receive_net_wt')
                    ->withSum('items as loss_wt_sum', 'loss_wt'),
            ])
            ->withCount('items')
            ->withSum('items as issue_net_wt_sum', 'net_wt')
            ->when($fromDate, fn($q) => $q->whereDate('jobwork_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('jobwork_date', '<=', $toDate))
            ->when($workerId, fn($q) => $q->where('job_worker_id', (int) $workerId))
            ->latest('jobwork_date')
            ->latest('id');
    }

    private function receiveVoucherDataTable($rows, Company $company, bool $showPdf = true)
    {
        return DataTables::of($rows)
            ->addIndexColumn()
            ->editColumn('voucher_no', function ($row) use ($company) {
                $id = Crypt::encryptString((string) $row->id);
                $viewUrl = route('company.jobwork-receive.show', [$company->slug, $id]);

                return '<a href="' . $viewUrl . '" class="text-info fw-semibold">' . e($row->voucher_no) . '</a>';
            })
            ->addColumn('jobwork_date_view', fn($row) => optional($row->jobwork_date)->format('d-m-Y') ?? '-')
            ->addColumn('jobworker_name', fn($row) => $row->jobWorker?->name ?? '-')
            ->addColumn('production_step_name', fn($row) => $row->productionStep?->name ?? '-')
            ->addColumn('issue_net_wt_sum', fn($row) => number_format((float) ($row->issue_net_wt_sum ?? 0), 3, '.', ''))
            ->addColumn('receive_net_wt_sum', fn($row) => number_format((float) ($row->receive?->receive_net_wt_sum ?? 0), 3, '.', ''))
            ->addColumn('pending_net_wt', fn($row) => number_format(max(0, (float) ($row->issue_net_wt_sum ?? 0) - (float) ($row->receive?->receive_net_wt_sum ?? 0)), 3, '.', ''))
            ->addColumn('status', function ($row) {
                $issueWt = (float) ($row->issue_net_wt_sum ?? 0);
                $receiveWt = (float) ($row->receive?->receive_net_wt_sum ?? 0);
                $pendingWt = max(0, $issueWt - $receiveWt);

                if ($issueWt > 0 && $pendingWt <= 0.0005) {
                    return '<span class="badge bg-success">Completed</span>';
                }

                if ($receiveWt > 0) {
                    return '<span class="badge bg-warning text-dark">Partial</span>';
                }

                return '<span class="badge bg-danger">Pending</span>';
            })
            ->addColumn('assigned_receive', fn($row) => $this->assignedReceiveCount($row))
            ->addColumn('pending', fn($row) => max(0, (int) ($row->items_count ?? 0) - $this->assignedReceiveCount($row)))
            ->addColumn('action', function ($row) use ($company, $showPdf) {
                $id = Crypt::encryptString((string) $row->id);
                $viewUrl = route('company.jobwork-receive.show', [$company->slug, $id]);
                $issueWt = (float) ($row->issue_net_wt_sum ?? 0);
                $receiveWt = (float) ($row->receive?->receive_net_wt_sum ?? 0);
                $pendingWt = max(0, $issueWt - $receiveWt);
                $label = $pendingWt > 0.0005 ? 'Receive' : 'View';

                $buttons = '<a href="' . $viewUrl . '" class="btn btn-sm btn-info">' . $label . '</a>';

                if ($showPdf) {
                    $pdfUrl = route('company.jobwork-receive.pdf', [$company->slug, $id]);
                    $buttons .= '<a href="' . $pdfUrl . '" class="btn btn-sm btn-success">PDF</a>';
                }

                return '<div class="d-flex flex-wrap gap-1 align-items-center">' . $buttons . '</div>';
            })
            ->rawColumns(['voucher_no', 'status', 'action'])
            ->make(true);
    }

    private function jobWorkers(Company $company)
    {
        return WorkerPersonService::activeWorkers((int) $company->id);
    }

    private function findIssue(Company $company, int $id): JobworkIssue
    {
        return JobworkIssue::query()
            ->where('company_id', $company->id)
            ->whereHas('items')
            ->with([
                'jobWorker:id,name',
                'productionStep:id,name',
                'createdByUser:id,name',
                'items.item:id,item_name',
                'items.otherCharge:id,other_charge',
                'items.receiveItem',
            ])
            ->withSum('items as gross_wt_sum', 'gross_wt')
            ->withSum('items as net_wt_sum', 'net_wt')
            ->withSum('items as fine_wt_sum', 'fine_wt')
            ->withSum('items as total_amt_sum', 'total_amt')
            ->findOrFail($id);
    }

    private function ensureReceive(Company $company, JobworkIssue $row): JobworkReceive
    {
        return JobworkReceive::firstOrCreate(
            [
                'company_id' => $company->id,
                'jobwork_issue_id' => $row->id,
            ],
            [
                'receive_date' => now()->toDateString(),
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'modified_count' => 0,
            ]
        )->load(['items.item', 'items.jobworkIssueItem.item']);
    }

    private function assignedReceiveCount(JobworkIssue $row): int
    {
        return (int) ($row->receive?->assigned_receive_count ?? 0);
    }

    private function issueItemOptions(JobworkIssue $row)
    {
        return $row->items
            ->groupBy('item_id')
            ->map(function ($items) {
                $first = $items->first();
                $issueNet = (float) $items->sum('net_wt');
                $issueQty = (int) $items->sum('qty_pcs');
                $purity = (float) ($first->net_purity ?: $first->purity ?: 0);

                return [
                    'id' => $first->id,
                    'item_id' => $first->item_id,
                    'item_name' => $first->item?->item_name ?? '-',
                    'issue_net_wt' => $issueNet,
                    'issue_qty' => $issueQty,
                    'purity' => $purity,
                    'net_purity' => $purity,
                ];
            })
            ->values();
    }

    private function receiveItemOptions(Company $company, $issueItemOptions)
    {
        $issueByItemId = collect($issueItemOptions)->keyBy('item_id');

        return Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'outward_purity'])
            ->map(function ($item) use ($issueByItemId) {
                $issue = $issueByItemId->get($item->id);
                $purity = (float) ($issue['purity'] ?? $item->outward_purity ?? 0);

                return [
                    'id' => $item->id,
                    'item_name' => $item->item_name,
                    'issue_item_id' => $issue['id'] ?? null,
                    'issue_net_wt' => (float) ($issue['issue_net_wt'] ?? 0),
                    'issue_qty' => (int) ($issue['issue_qty'] ?? 0),
                    'purity' => $purity,
                    'net_purity' => $purity,
                ];
            })
            ->values();
    }

    private function workerIssueVouchers(Company $company, JobworkIssue $row)
    {
        return JobworkIssue::query()
            ->where('company_id', $company->id)
            ->where('job_worker_id', $row->job_worker_id)
            ->whereHas('items')
            ->with([
                'productionStep:id,name',
                'receive' => fn($query) => $query
                    ->withSum('items as receive_net_wt_sum', 'receive_net_wt'),
            ])
            ->withSum('items as issue_net_wt_sum', 'net_wt')
            ->orderByDesc('jobwork_date')
            ->orderByDesc('id')
            ->get();
    }

    private function otherChargeOptions(Company $company)
    {
        return OtherCharge::where('company_id', $company->id)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id')
            ->get([
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
                'item_id',
            ])
            ->map(fn($charge) => [
                'id' => (int) $charge->id,
                'name' => $charge->other_charge,
                'default_amount' => (float) ($charge->default_amount ?? 0),
                'default_weight' => (float) ($charge->default_weight ?? 0),
                'quantity_pcs' => (float) ($charge->quantity_pcs ?? 1),
                'weight_formula' => $charge->weight_formula ?: 'flat',
                'weight_percent' => (float) ($charge->weight_percent ?? 0),
                'other_amt_formula' => $charge->other_amt_formula ?: 'flat',
                'wt_operation' => $charge->wt_operation ?: 'less',
                'is_default' => (bool) ($charge->is_default ?? false),
                'is_selected' => (bool) ($charge->is_selected ?? false),
                'item_id' => $charge->item_id ? (int) $charge->item_id : null,
            ])
            ->values();
    }

    private function createReceiveItems(JobworkReceive $receive, JobworkIssue $issue, array $items): void
    {
        $issueItemByItem = $issue->items->keyBy('item_id');
        $issueNetByItem = $issue->items
            ->groupBy('item_id')
            ->map(fn($rows) => (float) $rows->sum('net_wt'));

        $preparedRows = collect($items)
            ->filter(fn($item) => !empty($item['item_id']))
            ->map(function ($item) {
                $receiveGross = (float) ($item['receive_gross_wt'] ?? 0);
                $otherWt = (float) ($item['other_wt'] ?? 0);
                $receiveNet = max(0, $receiveGross - $otherWt);

                if ($receiveGross <= 0 && isset($item['receive_net_wt'])) {
                    $receiveNet = max(0, (float) $item['receive_net_wt']);
                }

                return [
                    'source' => $item,
                    'item_id' => (int) $item['item_id'],
                    'receive_gross_wt' => $receiveGross,
                    'other_wt' => $otherWt,
                    'receive_net_wt' => $receiveNet,
                    'receive_qty_pcs' => (int) ($item['receive_qty_pcs'] ?? 0),
                    'remarks' => $item['remarks'] ?? null,
                ];
            })
            ->filter(function ($row) {
                return $row['receive_gross_wt'] > 0
                    || $row['receive_net_wt'] > 0
                    || $row['receive_qty_pcs'] > 0
                    || filled($row['remarks']);
            })
            ->values();

        $lossByItem = $preparedRows
            ->groupBy('item_id')
            ->map(fn($rows, $itemId) => max(0, (float) ($issueNetByItem->get($itemId, 0)) - (float) $rows->sum('receive_net_wt')));
        $lossAssigned = [];

        foreach ($preparedRows as $prepared) {
            $item = $prepared['source'];
            $itemId = $prepared['item_id'];
            $issueItem = $issueItemByItem->get($itemId);
            $loss = empty($lossAssigned[$itemId]) ? (float) ($lossByItem->get($itemId, 0) ?? 0) : 0.0;
            $lossAssigned[$itemId] = true;

            $receive->items()->create([
                'jobwork_issue_item_id' => $issueItem?->id,
                'item_id' => $itemId,
                'receive_gross_wt' => $prepared['receive_gross_wt'],
                'other_wt' => $prepared['other_wt'],
                'other_amt' => (float) ($item['other_amt'] ?? 0),
                'other_charge_details' => $item['other_charge_details'] ?? null,
                'receive_net_wt' => $prepared['receive_net_wt'],
                'receive_fine_wt' => (float) ($item['receive_fine_wt'] ?? 0),
                'receive_qty_pcs' => $prepared['receive_qty_pcs'],
                'loss_wt' => $loss,
                'remarks' => $prepared['remarks'],
            ]);
        }
    }
}
