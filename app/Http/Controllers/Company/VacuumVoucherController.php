<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\VacuumBuch;
use App\Models\VacuumProcess;
use App\Models\VacuumVoucher;
use App\Models\VacuumVoucherItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class VacuumVoucherController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $rows = VacuumVoucher::query()
                ->where('company_id', $company->id)
                ->when($fromDate, fn($q) => $q->whereDate('voucher_date', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('voucher_date', '<=', $toDate))
                ->when($workerId, fn($q) => $q->where('job_worker_id', $workerId))
                ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name'])
                ->select('vacuum_vouchers.*')
                ->orderByDesc('created_at')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_date_view', fn($row) => optional($row->created_at)->format('d-m-Y  / h:i A') ?? '-')
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('gross_wt_total_view', fn($row) => $this->fmt($row->gross_wt_total, 3))
                ->addColumn('buch_wt_total_view', fn($row) => $this->fmt($row->buch_wt_total, 3))
                ->addColumn('net_wt_total_view', fn($row) => $this->fmt($row->net_wt_total, 3))
                ->addColumn('silver_wt_total_view', fn($row) => $this->fmt($row->silver_wt_total, 3))
                ->addColumn('user_name', fn($row) => $row->createdByUser?->name ?? '-')
                ->addColumn('modified_at_view', fn($row) => optional($row->updated_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);
                    $view = route('company.vacuum-vouchers.show', [$company->slug, $encryptedId]);
                    $pdf = route('company.vacuum-vouchers.pdf', [$company->slug, $encryptedId]);
                    $edit = route('company.vacuum-vouchers.edit', [$company->slug, $encryptedId]);
                    $delete = route('company.vacuum-vouchers.destroy', [$company->slug, $encryptedId]);

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($delete) . '">Delete</button>
                    </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.vacuum_vouchers.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $data = null;

        return view('company.vacuum_vouchers.create', $this->formData($company, $data));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $unavailableBuchs = $this->submittedUnavailableBuchNumbers($request, $company->id);
        if ($unavailableBuchs !== []) {
            $message = 'This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs);

            return back()
                ->withErrors(['items' => $message])
                ->with('error', $message)
                ->withInput();
        }

        $validated = $this->validatePayload($request, $company->id);

        DB::transaction(function () use ($validated, $company) {
            $totals = $this->calculatedRowsAndTotals($validated['items'], (float) $validated['formula_value']);

            $voucher = VacuumVoucher::create([
                'company_id' => $company->id,
                'voucher_no' => $this->generateVoucherNo($company->id, $validated['voucher_date']),
                'voucher_date' => $validated['voucher_date'],
                'vacuum_process_id' => $validated['vacuum_process_id'],
                'job_worker_id' => $validated['job_worker_id'],
                'formula_value' => (float) $validated['formula_value'],
                'gross_wt_total' => $totals['gross_wt_total'],
                'buch_wt_total' => $totals['buch_wt_total'],
                'net_wt_total' => $totals['net_wt_total'],
                'silver_wt_total' => $totals['silver_wt_total'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => auth()->id(),
                'updated_by' => auth()->id(),
                'modified_count' => 0,
            ]);

            foreach ($totals['rows'] as $row) {
                $voucher->items()->create($row);
            }
        });

        return redirect()
            ->route('company.vacuum-vouchers.index', $company->slug)
            ->with('success', 'Vacuum Voucher created successfully');
    }

    public function show($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name', 'items'])
            ->findOrFail($id);

        return view('company.vacuum_vouchers.show', compact('company', 'data'));
    }

    public function pdf($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name', 'items'])
            ->findOrFail($id);

        return Pdf::loadView('company.vacuum_vouchers.pdf.show', compact('company', 'data'))
            ->setPaper('a4', 'portrait')
            ->download('vacuum_voucher_' . $data->voucher_no . '.pdf');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumVoucher::where('company_id', $company->id)->with('items')->findOrFail($id);

        return view('company.vacuum_vouchers.create', $this->formData($company, $data));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->findOrFail($id);
        $unavailableBuchs = $this->submittedUnavailableBuchNumbers($request, $company->id, (int) $voucher->id);
        if ($unavailableBuchs !== []) {
            $message = 'This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs);

            return back()
                ->withErrors(['items' => $message])
                ->with('error', $message)
                ->withInput();
        }

        $validated = $this->validatePayload($request, $company->id, (int) $voucher->id);

        DB::transaction(function () use ($voucher, $validated) {
            $totals = $this->calculatedRowsAndTotals($validated['items'], (float) $validated['formula_value']);

            $voucher->update([
                'voucher_date' => $validated['voucher_date'],
                'vacuum_process_id' => $validated['vacuum_process_id'],
                'job_worker_id' => $validated['job_worker_id'],
                'formula_value' => (float) $validated['formula_value'],
                'gross_wt_total' => $totals['gross_wt_total'],
                'buch_wt_total' => $totals['buch_wt_total'],
                'net_wt_total' => $totals['net_wt_total'],
                'silver_wt_total' => $totals['silver_wt_total'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => auth()->id(),
                'modified_count' => ((int) $voucher->modified_count) + 1,
            ]);

            $voucher->items()->delete();
            foreach ($totals['rows'] as $row) {
                $voucher->items()->create($row);
            }
        });

        return redirect()
            ->route('company.vacuum-vouchers.index', $company->slug)
            ->with('success', 'Vacuum Voucher updated successfully');
    }

    public function destroy(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        VacuumVoucher::where('company_id', $company->id)->findOrFail($id)->delete();

        if ($request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Vacuum Voucher deleted successfully']);
        }

        return redirect()->route('company.vacuum-vouchers.index', $company->slug)
            ->with('success', 'Vacuum Voucher deleted successfully');
    }

    public function buchOptions(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $term = trim((string) $request->get('q', ''));
        $currentVoucherId = $request->filled('current_voucher_id')
            ? (int) $request->get('current_voucher_id')
            : null;
        $usedBuchIds = $this->usedBuchIds($company->id, $currentVoucherId);

        return VacuumBuch::where('company_id', $company->id)
            ->when($usedBuchIds !== [], fn($q) => $q->whereNotIn('id', $usedBuchIds))
            ->when($term !== '', fn($q) => $q->where('buch_no', 'like', '%' . $term . '%'))
            ->orderBy('buch_no')
            ->limit(30)
            ->get(['id', 'buch_no', 'weight'])
            ->map(fn($row) => [
                'id' => $row->id,
                'text' => $row->buch_no,
                'weight' => (float) ($row->weight ?? 0),
            ]);
    }

    private function formData(Company $company, ?VacuumVoucher $data): array
    {
        $currentVoucherId = $data ? (int) $data->id : null;
        $usedBuchIds = $this->usedBuchIds($company->id, $currentVoucherId);

        return [
            'company' => $company,
            'data' => $data,
            'processes' => VacuumProcess::where('company_id', $company->id)->orderBy('name')->get(['id', 'name']),
            'jobWorkers' => JobWorker::where('company_id', $company->id)->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'buchs' => VacuumBuch::where('company_id', $company->id)
                ->when($usedBuchIds !== [], fn($q) => $q->whereNotIn('id', $usedBuchIds))
                ->orderBy('buch_no')
                ->get(['id', 'buch_no', 'weight']),
        ];
    }

    private function validatePayload(Request $request, int $companyId, ?int $voucherId = null): array
    {
        $items = collect((array) $request->input('items', []))
            ->filter(fn($row) => filled($row['vacuum_buch_id'] ?? null))
            ->values()
            ->all();

        $request->merge(['items' => $items]);
        $usedBuchIds = $this->usedBuchIds($companyId, $voucherId);

        return $request->validate([
            'voucher_date' => ['required', 'date'],
            'vacuum_process_id' => [
                'required',
                'integer',
                Rule::exists('vacuum_processes', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'job_worker_id' => [
                'required',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'formula_value' => ['required', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.vacuum_buch_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('vacuum_buchs', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
                Rule::notIn($usedBuchIds),
            ],
            'items.*.gross_wt' => ['required', 'numeric'],
            'items.*.buch_wt' => ['required', 'numeric'],
        ], [
            'items.*.vacuum_buch_id.distinct' => 'Each Buch No can be selected only one time in the voucher.',
            'items.*.vacuum_buch_id.not_in' => 'Selected Buch No is already used in another voucher.',
        ]);
    }

    private function usedBuchIds(int $companyId, ?int $ignoreVoucherId = null): array
    {
        return VacuumVoucherItem::whereHas('voucher', function ($query) use ($companyId, $ignoreVoucherId) {
            $query->where('company_id', $companyId)
                ->when($ignoreVoucherId, fn($q) => $q->where('id', '!=', $ignoreVoucherId));
        })
            ->pluck('vacuum_buch_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function submittedUnavailableBuchNumbers(Request $request, int $companyId, ?int $ignoreVoucherId = null): array
    {
        $submittedIds = collect((array) $request->input('items', []))
            ->pluck('vacuum_buch_id')
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($submittedIds->isEmpty()) {
            return [];
        }

        $usedIds = $this->usedBuchIds($companyId, $ignoreVoucherId);
        $unavailableIds = $submittedIds->intersect($usedIds)->values();

        if ($unavailableIds->isEmpty()) {
            return [];
        }

        return VacuumBuch::where('company_id', $companyId)
            ->whereIn('id', $unavailableIds)
            ->orderBy('buch_no')
            ->pluck('buch_no')
            ->all();
    }

    private function calculatedRowsAndTotals(array $items, float $formula): array
    {
        $rows = [];
        $grossTotal = 0;
        $buchTotal = 0;
        $netTotal = 0;
        $silverTotal = 0;

        foreach ($items as $item) {
            $gross = round((float) ($item['gross_wt'] ?? 0), 3);
            $buchWt = round((float) ($item['buch_wt'] ?? 0), 3);
            $net = round($gross - $buchWt, 3);
            $silver = round($net * $formula, 3);
            $buch = VacuumBuch::find($item['vacuum_buch_id']);

            $rows[] = [
                'vacuum_buch_id' => $item['vacuum_buch_id'],
                'buch_no' => $buch?->buch_no,
                'gross_wt' => $gross,
                'buch_wt' => $buchWt,
                'net_wt' => $net,
                'silver_wt' => $silver,
            ];

            $grossTotal += $gross;
            $buchTotal += $buchWt;
            $netTotal += $net;
            $silverTotal += $silver;
        }

        return [
            'rows' => $rows,
            'gross_wt_total' => round($grossTotal, 3),
            'buch_wt_total' => round($buchTotal, 3),
            'net_wt_total' => round($netTotal, 3),
            'silver_wt_total' => round($silverTotal, 3),
        ];
    }

    private function generateVoucherNo(int $companyId, string $date): string
    {
        $prefix = 'VV' . Carbon::parse($date)->format('y') . '-';
        $last = VacuumVoucher::where('company_id', $companyId)
            ->where('voucher_no', 'like', $prefix . '%')
            ->orderByDesc('id')
            ->value('voucher_no');

        $next = 1;
        if ($last && str_contains($last, '-')) {
            $parts = explode('-', $last);
            $next = ((int) end($parts)) + 1;
        }

        return $prefix . $next;
    }

    private function fmt($value, int $decimals): string
    {
        return number_format((float) ($value ?? 0), $decimals, '.', '');
    }
}
