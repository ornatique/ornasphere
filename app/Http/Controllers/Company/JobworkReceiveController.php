<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Item;
use App\Models\JobworkIssue;
use App\Models\JobworkReceive;
use App\Models\OtherCharge;
use App\Models\ProductionStep;
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
            return $this->receiveListDataTable($company, $request);
        }

        $jobWorkers = $this->jobWorkers($company);
        $customers = $this->customers($company);

        return view('company.jobwork_receive.index', compact('company', 'jobWorkers', 'customers'));
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
        $productionSteps = ProductionStep::where('company_id', $company->id)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name']);
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'inward_purity', 'labour_rate']);

        return view('company.jobwork_receive.create', compact('company', 'jobWorkers', 'productionSteps', 'items'));
    }

    public function storeDirect(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $validated = $this->validateDirectReceiveData($request, (int) $company->id);

        $receive = DB::transaction(function () use ($validated, $company) {
            $receive = JobworkReceive::create([
                'company_id' => $company->id,
                'jobwork_issue_id' => null,
                'receive_no' => $this->generateDirectReceiveNo((int) $company->id, $validated['receive_date']),
                'receive_date' => $validated['receive_date'],
                'job_worker_id' => null,
                'customer_id' => $validated['customer_id'],
                'production_step_id' => null,
                'receive_type' => 'direct',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'modified_count' => 0,
            ]);

            $this->syncDirectReceiveItems($receive, $validated['items']);

            return $receive;
        });

        return redirect()
            ->route('company.jobwork-receive.index', $company->slug)
            ->with('success', 'Direct Jobwork Receive created successfully');
    }

    public function createDirect(string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $jobWorkers = $this->jobWorkers($company);
        $customers = $this->customers($company);
        $productionSteps = ProductionStep::where('company_id', $company->id)
            ->where('status', true)
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'inward_purity', 'labour_rate']);
        $otherCharges = $this->otherChargeOptions($company);

        return view('company.jobwork_receive.direct', compact('company', 'jobWorkers', 'customers', 'productionSteps', 'items', 'otherCharges'));
    }

    public function editDirect(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $receive = $this->findDirectReceive($company, (int) Crypt::decryptString($encryptedId));
        $jobWorkers = $this->jobWorkers($company);
        $customers = $this->customers($company);
        $productionSteps = ProductionStep::where('company_id', $company->id)
            ->where(function ($query) use ($receive) {
                $query->where('status', true)
                    ->orWhere('id', $receive->production_step_id);
            })
            ->orderBy('name')
            ->get(['id', 'name', 'status']);
        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'inward_purity', 'labour_rate']);
        $otherCharges = $this->otherChargeOptions($company);

        return view('company.jobwork_receive.direct', compact('company', 'receive', 'jobWorkers', 'customers', 'productionSteps', 'items', 'otherCharges'));
    }

    public function updateDirect(Request $request, string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $receive = $this->findDirectReceive($company, (int) Crypt::decryptString($encryptedId));
        $validated = $this->validateDirectReceiveData($request, (int) $company->id);

        DB::transaction(function () use ($receive, $validated) {
            $receive->update([
                'receive_date' => $validated['receive_date'],
                'job_worker_id' => null,
                'customer_id' => $validated['customer_id'],
                'production_step_id' => null,
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);

            $this->syncDirectReceiveItems($receive, $validated['items']);
        });

        return redirect()
            ->route('company.jobwork-receive.index', $company->slug)
            ->with('success', 'Direct Jobwork Receive updated successfully');
    }

    public function directPdf(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $receive = $this->findDirectReceive($company, (int) Crypt::decryptString($encryptedId));

        return Pdf::loadView('company.jobwork_receive.pdf.direct', compact('company', 'receive'))
            ->setPaper('a4', 'portrait')
            ->download('direct_jobwork_receive_' . ($receive->receive_no ?: $receive->id) . '.pdf');
    }

    public function destroyDirect(string $slug, string $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $receive = $this->findDirectReceive($company, (int) Crypt::decryptString($encryptedId));

        DB::transaction(function () use ($receive) {
            $receive->items()->delete();
            $receive->delete();
        });

        return redirect()
            ->route('company.jobwork-receive.index', $company->slug)
            ->with('success', 'Direct Jobwork Receive deleted successfully');
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
            ->setPaper('a4', 'portrait')
            ->download('jobwork_receive_' . $row->voucher_no . '.pdf');
    }

    public function exportPdf(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = $this->reportRows($company, $request);

        return Pdf::loadView('company.jobwork_receive.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_receive_report.pdf');
    }

    private function baseQuery(Company $company, Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $workerId = $request->input('worker_id');
        $status = $request->input('status');
        $searchText = trim((string) $request->input('search_text', ''));
        $issueNetSql = '(select coalesce(sum(jii.net_wt), 0) from jobwork_issue_items jii where jii.jobwork_issue_id = jobwork_issues.id)';
        $receiveNetSql = '(select coalesce(sum(jri.receive_net_wt), 0) from jobwork_receives jr inner join jobwork_receive_items jri on jri.jobwork_receive_id = jr.id where jr.jobwork_issue_id = jobwork_issues.id)';

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
            ->when($searchText !== '', fn($q) => $q->where(function ($query) use ($searchText) {
                $query->where('voucher_no', 'like', '%' . $searchText . '%')
                    ->orWhereHas('jobWorker', fn($workerQuery) => $workerQuery->where('name', 'like', '%' . $searchText . '%'))
                    ->orWhereHas('productionStep', fn($stepQuery) => $stepQuery->where('name', 'like', '%' . $searchText . '%'));
            }))
            ->when($status === 'direct', fn($q) => $q->whereRaw('1 = 0'))
            ->when($status === 'pending', fn($q) => $q
                ->whereRaw($issueNetSql . ' > 0')
                ->whereRaw($receiveNetSql . ' <= 0.0005'))
            ->when($status === 'partial', fn($q) => $q
                ->whereRaw($receiveNetSql . ' > 0.0005')
                ->whereRaw('(' . $issueNetSql . ' - ' . $receiveNetSql . ') > 0.0005'))
            ->when($status === 'completed', fn($q) => $q
                ->whereRaw($issueNetSql . ' > 0')
                ->whereRaw('(' . $issueNetSql . ' - ' . $receiveNetSql . ') <= 0.0005'))
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

    private function receiveListDataTable(Company $company, Request $request)
    {
        $rows = $this->reportRows($company, $request);
        $total = $rows->count();
        $start = max(0, (int) $request->input('start', 0));
        $length = (int) $request->input('length', 10);
        $pagedRows = ($length > 0 ? $rows->slice($start, $length) : $rows)
            ->values()
            ->map(function ($row, $index) use ($start) {
                unset($row['sort_at']);
                $row['DT_RowIndex'] = $start + $index + 1;

                return $row;
            });

        return response()->json([
            'draw' => (int) $request->input('draw', 0),
            'recordsTotal' => $total,
            'recordsFiltered' => $total,
            'data' => $pagedRows,
        ]);
    }

    private function reportRows(Company $company, Request $request)
    {
        $status = (string) $request->input('status', '');
        $issueRows = collect();
        $directRows = collect();

        if ($status !== 'direct') {
            $issueRows = $this->baseQuery($company, $request)
                ->get()
                ->map(fn($row) => $this->formatIssueReceiveListRow($company, $row));
        }

        if ($status === '' || $status === 'direct') {
            $directRows = $this->directReceiveBaseQuery($company, $request)
                ->get()
                ->map(fn($row) => $this->formatDirectReceiveListRow($company, $row));
        }

        return $issueRows
            ->merge($directRows)
            ->sortByDesc('sort_at')
            ->values();
    }

    private function formatIssueReceiveListRow(Company $company, JobworkIssue $row): array
    {
        $id = Crypt::encryptString((string) $row->id);
        $viewUrl = route('company.jobwork-receive.show', [$company->slug, $id]);
        $pdfUrl = route('company.jobwork-receive.pdf', [$company->slug, $id]);
        $issueWt = (float) ($row->issue_net_wt_sum ?? 0);
        $receiveWt = (float) ($row->receive?->receive_net_wt_sum ?? 0);
        $pendingWt = max(0, $issueWt - $receiveWt);
        $status = '<span class="badge bg-danger">Pending</span>';

        if ($issueWt > 0 && $pendingWt <= 0.0005) {
            $status = '<span class="badge bg-success">Completed</span>';
        } elseif ($receiveWt > 0) {
            $status = '<span class="badge bg-warning text-dark">Partial</span>';
        }

        $label = $pendingWt > 0.0005 ? 'Receive' : 'View';

        return [
            'voucher_no' => '<a href="' . $viewUrl . '" class="text-info fw-semibold">' . e($row->voucher_no) . '</a>',
            'jobwork_date_view' => optional($row->jobwork_date)->format('d-m-Y') ?? '-',
            'jobworker_name' => $row->jobWorker?->name ?? '-',
            'production_step_name' => $row->productionStep?->name ?? '-',
            'issue_net_wt_sum' => number_format($issueWt, 3, '.', ''),
            'receive_net_wt_sum' => number_format($receiveWt, 3, '.', ''),
            'pending_net_wt' => number_format($pendingWt, 3, '.', ''),
            'status' => $status,
            'action' => '<div class="d-flex flex-wrap gap-1 align-items-center"><a href="' . $viewUrl . '" class="btn btn-sm btn-info">' . $label . '</a><a href="' . $pdfUrl . '" class="btn btn-sm btn-success">PDF</a></div>',
            'sort_at' => optional($row->created_at)->timestamp ?? 0,
        ];
    }

    private function formatDirectReceiveListRow(Company $company, JobworkReceive $row): array
    {
        $id = Crypt::encryptString((string) $row->id);
        $editUrl = route('company.jobwork-receive.direct.edit', [$company->slug, $id]);
        $pdfUrl = route('company.jobwork-receive.direct.pdf', [$company->slug, $id]);
        $deleteUrl = route('company.jobwork-receive.direct.destroy', [$company->slug, $id]);
        $receiveWt = (float) ($row->receive_net_wt_sum ?? 0);
        $voucherNo = $row->receive_no ?: 'Direct-' . $row->id;
        $deleteForm = '<form method="POST" action="' . $deleteUrl . '" style="display:inline" onsubmit="return confirm(\'Delete this direct receive voucher?\')">'
            . csrf_field()
            . method_field('DELETE')
            . '<button type="submit" class="btn btn-sm btn-danger">Delete</button>'
            . '</form>';

        return [
            'voucher_no' => '<a href="' . $editUrl . '" class="text-info fw-semibold">' . e($voucherNo) . '</a>',
            'jobwork_date_view' => optional($row->receive_date)->format('d-m-Y') ?? '-',
            'jobworker_name' => $row->customer?->name ?? $row->jobWorker?->name ?? '-',
            'production_step_name' => $row->productionStep?->name ?? '-',
            'issue_net_wt_sum' => '0.000',
            'receive_net_wt_sum' => number_format($receiveWt, 3, '.', ''),
            'pending_net_wt' => '0.000',
            'status' => '<span class="badge bg-info">Direct</span>',
            'action' => '<div class="d-flex flex-wrap gap-1 align-items-center"><a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a><a href="' . $pdfUrl . '" class="btn btn-sm btn-success">PDF</a>' . $deleteForm . '</div>',
            'sort_at' => optional($row->created_at)->timestamp ?? 0,
        ];
    }

    private function jobWorkers(Company $company)
    {
        return WorkerPersonService::activeWorkers((int) $company->id);
    }

    private function customers(Company $company)
    {
        return Customer::where('company_id', $company->id)
            ->where('is_active', true)
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

    private function directReceiveBaseQuery(Company $company, Request $request)
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $workerId = $request->input('worker_id');
        $customerId = $request->input('customer_id');
        $status = $request->input('status');
        $searchText = trim((string) ($request->input('search_text') ?: data_get($request->input('search'), 'value', '')));

        return JobworkReceive::query()
            ->where('company_id', $company->id)
            ->where('receive_type', 'direct')
            ->with(['jobWorker:id,name', 'customer:id,name', 'productionStep:id,name'])
            ->withSum('items as receive_net_wt_sum', 'receive_net_wt')
            ->when($fromDate, fn($q) => $q->whereDate('receive_date', '>=', $fromDate))
            ->when($toDate, fn($q) => $q->whereDate('receive_date', '<=', $toDate))
            ->when($workerId, fn($q) => $q->where('job_worker_id', (int) $workerId))
            ->when($customerId, fn($q) => $q->where('customer_id', (int) $customerId))
            ->when($status, function ($q) use ($status) {
                if ($status === 'completed') {
                    $q->whereRaw('1 = 0');
                } elseif ($status === 'pending' || $status === 'partial') {
                    $q->whereRaw('1 = 0');
                }
            })
            ->when($searchText !== '', fn($q) => $q->where(function ($query) use ($searchText) {
                $query->where('receive_no', 'like', '%' . $searchText . '%')
                    ->orWhereHas('jobWorker', fn($workerQuery) => $workerQuery->where('name', 'like', '%' . $searchText . '%'))
                    ->orWhereHas('customer', fn($customerQuery) => $customerQuery->where('name', 'like', '%' . $searchText . '%'))
                    ->orWhereHas('productionStep', fn($stepQuery) => $stepQuery->where('name', 'like', '%' . $searchText . '%'));
            }))
            ->latest('created_at')
            ->latest('id');
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
                'job_worker_id' => $row->job_worker_id,
                'production_step_id' => $row->production_step_id,
                'receive_type' => 'issue',
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
                $issuePurity = (float) ($first->net_purity ?: $first->purity ?: 0);
                $itemPurity = (float) ($first->item?->outward_purity ?: $first->item?->inward_purity ?: 0);
                $purity = $issuePurity > 0 ? $issuePurity : $itemPurity;

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
            ->get(['id', 'item_name', 'outward_purity', 'inward_purity'])
            ->map(function ($item) use ($issueByItemId) {
                $issue = $issueByItemId->get($item->id);
                $issuePurity = (float) ($issue['purity'] ?? 0);
                $itemPurity = (float) ($item->outward_purity ?: $item->inward_purity ?: 0);
                $purity = $issuePurity > 0 ? $issuePurity : $itemPurity;

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

    private function findDirectReceive(Company $company, int $id): JobworkReceive
    {
        return JobworkReceive::query()
            ->where('company_id', $company->id)
            ->where('receive_type', 'direct')
            ->with(['jobWorker:id,name', 'customer:id,name', 'productionStep:id,name', 'items.item:id,item_name,item_code'])
            ->findOrFail($id);
    }

    private function validateDirectReceiveData(Request $request, int $companyId): array
    {
        return $request->validate([
            'receive_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
            'production_step_id' => ['nullable', 'integer', Rule::exists('production_steps', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
            'items.*.receive_gross_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_amt' => ['nullable', 'numeric', 'min:0'],
            'items.*.purity' => ['nullable', 'numeric', 'min:0'],
            'items.*.waste_percent' => ['nullable', 'numeric', 'min:0'],
            'items.*.net_purity' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_net_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_fine_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.metal_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.metal_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.labour_rate' => ['nullable', 'numeric', 'min:0'],
            'items.*.labour_amount' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_qty_pcs' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
            'items.*.total_amount' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function generateDirectReceiveNo(int $companyId, string $receiveDate): string
    {
        $prefix = 'JWR' . date('ymd', strtotime($receiveDate));
        $count = JobworkReceive::where('company_id', $companyId)
            ->where('receive_type', 'direct')
            ->whereDate('receive_date', $receiveDate)
            ->count() + 1;

        return $prefix . '-' . str_pad((string) $count, 3, '0', STR_PAD_LEFT);
    }

    private function syncDirectReceiveItems(JobworkReceive $receive, array $items): void
    {
        $receive->items()->delete();

        foreach ($items as $item) {
            if (empty($item['item_id'])) {
                continue;
            }

            $receiveGross = (float) ($item['receive_gross_wt'] ?? 0);
            $otherWt = (float) ($item['other_wt'] ?? 0);
            $receiveNet = max(0, $receiveGross - $otherWt);
            $purity = (float) ($item['purity'] ?? 0);
            $wastePercent = (float) ($item['waste_percent'] ?? 0);
            $netPurity = (float) ($item['net_purity'] ?? ($purity + $wastePercent));

            if ($receiveGross <= 0 && isset($item['receive_net_wt'])) {
                $receiveNet = max(0, (float) $item['receive_net_wt']);
            }

            $receiveFine = (float) ($item['receive_fine_wt'] ?? ($receiveNet * $netPurity / 100));
            $metalRate = (float) ($item['metal_rate'] ?? 0);
            $metalAmount = (float) ($item['metal_amount'] ?? ($receiveFine * $metalRate));
            $labourRate = (float) ($item['labour_rate'] ?? 0);
            $labourAmount = (float) ($item['labour_amount'] ?? ($receiveNet * $labourRate));
            $otherAmount = (float) ($item['other_amt'] ?? 0);
            $totalAmount = (float) ($item['total_amount'] ?? ($metalAmount + $labourAmount + $otherAmount));

            if ($receiveGross <= 0 && $receiveNet <= 0 && empty($item['receive_qty_pcs']) && $totalAmount <= 0 && !filled($item['remarks'] ?? null)) {
                continue;
            }

            $receive->items()->create([
                'jobwork_issue_item_id' => null,
                'item_id' => (int) $item['item_id'],
                'receive_gross_wt' => $receiveGross,
                'other_wt' => $otherWt,
                'other_amt' => $otherAmount,
                'other_charge_details' => $item['other_charge_details'] ?? null,
                'purity' => $purity,
                'waste_percent' => $wastePercent,
                'net_purity' => $netPurity,
                'receive_net_wt' => $receiveNet,
                'receive_fine_wt' => $receiveFine,
                'metal_rate' => $metalRate,
                'metal_amount' => $metalAmount,
                'labour_rate' => $labourRate,
                'labour_amount' => $labourAmount,
                'receive_qty_pcs' => (int) ($item['receive_qty_pcs'] ?? 0),
                'loss_wt' => 0,
                'remarks' => $item['remarks'] ?? null,
                'total_amount' => $totalAmount,
            ]);
        }
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
