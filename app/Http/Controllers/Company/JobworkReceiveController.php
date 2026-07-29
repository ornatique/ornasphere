<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Models\JobWorker;
use App\Models\JobworkIssue;
use App\Models\JobworkReceive;
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

        return view('company.jobwork_receive.index', compact('company'));
    }

    public function create(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
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

        return view('company.jobwork_receive.show', compact('company', 'row', 'receive', 'issueItemOptions', 'receiveItemOptions'));
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
            'items' => ['required', 'array'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($query) => $query->where('company_id', $company->id)),
            ],
            'items.*.receive_gross_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_amt' => ['nullable', 'numeric', 'min:0'],
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

            $issueItemByItem = $row->items->keyBy('item_id');
            $issueNetByItem = $row->items
                ->groupBy('item_id')
                ->map(fn($items) => (float) $items->sum('net_wt'));
            $receive->items()->delete();

            foreach ($validated['items'] as $item) {
                if (empty($item['item_id'])) {
                    continue;
                }

                $itemId = (int) $item['item_id'];
                $issueItem = $issueItemByItem->get($itemId);

                $receiveGross = (float) ($item['receive_gross_wt'] ?? 0);
                $otherWt = (float) ($item['other_wt'] ?? 0);
                $receiveNet = max(0, $receiveGross - $otherWt);

                if ($receiveGross <= 0 && isset($item['receive_net_wt'])) {
                    $receiveNet = (float) $item['receive_net_wt'];
                }

                $loss = (float) ($issueNetByItem->get($itemId, 0)) - $receiveNet;
                $receiveQty = (int) ($item['receive_qty_pcs'] ?? 0);
                $remarks = $item['remarks'] ?? null;

                if ($receiveGross <= 0 && $receiveNet <= 0 && $receiveQty <= 0 && blank($remarks)) {
                    continue;
                }

                $receive->items()->create([
                    'jobwork_issue_item_id' => $issueItem?->id,
                    'item_id' => $itemId,
                    'receive_gross_wt' => $receiveGross,
                    'other_wt' => $otherWt,
                    'other_amt' => (float) ($item['other_amt'] ?? 0),
                    'receive_net_wt' => $receiveNet,
                    'receive_fine_wt' => (float) ($item['receive_fine_wt'] ?? 0),
                    'receive_qty_pcs' => $receiveQty,
                    'loss_wt' => $loss,
                    'remarks' => $remarks,
                ]);
            }
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

        return Pdf::loadView('company.jobwork_receive.pdf.show', compact('company', 'row', 'receive'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_receive_' . $row->voucher_no . '.pdf');
    }

    private function baseQuery(Company $company, Request $request)
    {
        $defaultFromDate = now()->subDays(6)->toDateString();
        $defaultToDate = now()->toDateString();
        $fromDate = $request->input('from_date', $defaultFromDate);
        $toDate = $request->input('to_date', $defaultToDate);
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
            ->addColumn('jobwork_date_view', fn($row) => optional($row->jobwork_date)->format('d-m-Y') ?? '-')
            ->addColumn('jobworker_name', fn($row) => $row->jobWorker?->name ?? '-')
            ->addColumn('production_step_name', fn($row) => $row->productionStep?->name ?? '-')
            ->addColumn('issue_net_wt_sum', fn($row) => number_format((float) ($row->issue_net_wt_sum ?? 0), 3, '.', ''))
            ->addColumn('receive_net_wt_sum', fn($row) => number_format((float) ($row->receive?->receive_net_wt_sum ?? 0), 3, '.', ''))
            ->addColumn('pending_net_wt', fn($row) => number_format((float) ($row->issue_net_wt_sum ?? 0) - (float) ($row->receive?->receive_net_wt_sum ?? 0), 3, '.', ''))
            ->addColumn('assigned_receive', fn($row) => $this->assignedReceiveCount($row))
            ->addColumn('pending', fn($row) => max(0, (int) ($row->items_count ?? 0) - $this->assignedReceiveCount($row)))
            ->addColumn('action', function ($row) use ($company, $showPdf) {
                $id = Crypt::encryptString((string) $row->id);
                $viewUrl = route('company.jobwork-receive.show', [$company->slug, $id]);

                $buttons = '<a href="' . $viewUrl . '" class="btn btn-sm btn-info">View</a>';

                if ($showPdf) {
                    $pdfUrl = route('company.jobwork-receive.pdf', [$company->slug, $id]);
                    $buttons .= '<a href="' . $pdfUrl . '" class="btn btn-sm btn-success">PDF</a>';
                }

                return '<div class="d-flex flex-wrap gap-1 align-items-center">' . $buttons . '</div>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    private function jobWorkers(Company $company)
    {
        return JobWorker::where('company_id', $company->id)
            ->orderBy('name')
            ->get(['id', 'name']);
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
}
