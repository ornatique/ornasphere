<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\VacuumBuch;
use App\Models\VacuumVoucher;
use App\Models\VacuumVoucherItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VacuumVoucherApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $rows = VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->when($fromDate, fn($query) => $query->whereDate('voucher_date', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->whereDate('voucher_date', '<=', $toDate))
            ->when($request->filled('worker_id'), fn($query) => $query->where('job_worker_id', (int) $request->input('worker_id')))
            ->when($request->filled('process_id'), fn($query) => $query->where('vacuum_process_id', (int) $request->input('process_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('process', fn($process) => $process->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobWorker', fn($worker) => $worker->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn($row) => $this->formatVoucher($row, false))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function buchOptions(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $term = trim((string) $request->input('q', ''));
        $currentVoucherId = $request->filled('current_voucher_id')
            ? (int) $request->input('current_voucher_id')
            : null;
        $usedBuchIds = $this->usedBuchIds($companyId, $currentVoucherId);

        $rows = VacuumBuch::where('company_id', $companyId)
            ->when($usedBuchIds !== [], fn($query) => $query->whereNotIn('id', $usedBuchIds))
            ->when($term !== '', fn($query) => $query->where('buch_no', 'like', "%{$term}%"))
            ->orderBy('buch_no')
            ->limit(30)
            ->get(['id', 'buch_no', 'weight'])
            ->map(fn($row) => [
                'id' => (int) $row->id,
                'text' => $row->buch_no,
                'buch_no' => $row->buch_no,
                'weight' => (float) ($row->weight ?? 0),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $data = $this->findVoucher($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Voucher not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher($data),
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $data = $this->findVoucher($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Voucher not found',
            ], 404);
        }

        $company = Company::findOrFail((int) $request->user()->company_id);

        return Pdf::loadView('company.vacuum_vouchers.pdf.show', compact('company', 'data'))
            ->setPaper('a4', 'portrait')
            ->download('vacuum_voucher_' . $data->voucher_no . '.pdf');
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $unavailableBuchs = $this->submittedUnavailableBuchNumbers($request, $companyId);
        if ($unavailableBuchs !== []) {
            return response()->json([
                'success' => false,
                'message' => 'This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs),
                'errors' => [
                    'items' => ['This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs)],
                ],
            ], 422);
        }

        $validated = $this->validatePayload($request, $companyId);
        $voucher = null;

        DB::transaction(function () use (&$voucher, $validated, $companyId, $request) {
            $totals = $this->calculatedRowsAndTotals($validated['items'], (float) $validated['formula_value'], $companyId);

            $voucher = VacuumVoucher::create([
                'company_id' => $companyId,
                'voucher_no' => $this->generateVoucherNo($companyId, $validated['voucher_date']),
                'voucher_date' => $validated['voucher_date'],
                'vacuum_process_id' => $validated['vacuum_process_id'],
                'job_worker_id' => $validated['job_worker_id'],
                'formula_value' => (float) $validated['formula_value'],
                'gross_wt_total' => $totals['gross_wt_total'],
                'buch_wt_total' => $totals['buch_wt_total'],
                'net_wt_total' => $totals['net_wt_total'],
                'silver_wt_total' => $totals['silver_wt_total'],
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => (int) $request->user()->id,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => 0,
            ]);

            foreach ($totals['rows'] as $row) {
                $voucher->items()->create($row);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Voucher created successfully',
            'data' => $this->formatVoucher($this->loadVoucher($voucher)),
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)->where('id', (int) $id)->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Voucher not found',
            ], 404);
        }

        $unavailableBuchs = $this->submittedUnavailableBuchNumbers($request, $companyId, (int) $voucher->id);
        if ($unavailableBuchs !== []) {
            return response()->json([
                'success' => false,
                'message' => 'This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs),
                'errors' => [
                    'items' => ['This Buch No is already used in another voucher: ' . implode(', ', $unavailableBuchs)],
                ],
            ], 422);
        }

        $validated = $this->validatePayload($request, $companyId, (int) $voucher->id);

        DB::transaction(function () use ($voucher, $validated, $companyId, $request) {
            $totals = $this->calculatedRowsAndTotals($validated['items'], (float) $validated['formula_value'], $companyId);

            $voucher->update([
                'vacuum_process_id' => $validated['vacuum_process_id'],
                'job_worker_id' => $validated['job_worker_id'],
                'formula_value' => (float) $validated['formula_value'],
                'gross_wt_total' => $totals['gross_wt_total'],
                'buch_wt_total' => $totals['buch_wt_total'],
                'net_wt_total' => $totals['net_wt_total'],
                'silver_wt_total' => $totals['silver_wt_total'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => ((int) $voucher->modified_count) + 1,
            ]);

            $voucher->items()->delete();
            foreach ($totals['rows'] as $row) {
                $voucher->items()->create($row);
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Voucher updated successfully',
            'data' => $this->formatVoucher($this->loadVoucher($voucher)),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $voucher = VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->where('id', (int) $id)
            ->first();

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Voucher not found',
            ], 404);
        }

        $voucher->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Voucher deleted successfully',
        ]);
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
                Rule::exists('vacuum_processes', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'job_worker_id' => [
                'required',
                'integer',
                Rule::exists('job_workers', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'formula_value' => ['required', 'numeric'],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.vacuum_buch_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('vacuum_buchs', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
                Rule::notIn($usedBuchIds),
            ],
            'items.*.gross_wt' => ['required', 'numeric'],
            'items.*.buch_wt' => ['required', 'numeric'],
        ], [
            'items.*.vacuum_buch_id.distinct' => 'Each Buch No can be selected only one time in the voucher.',
            'items.*.vacuum_buch_id.not_in' => 'Selected Buch No is already used in another pending voucher.',
        ]);
    }

    private function findVoucher(Request $request, int $id): ?VacuumVoucher
    {
        return VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->with(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name', 'updatedByUser:id,name', 'items.buch:id,buch_no,weight'])
            ->first();
    }

    private function loadVoucher(VacuumVoucher $voucher): VacuumVoucher
    {
        return $voucher->fresh(['process:id,name', 'jobWorker:id,name', 'createdByUser:id,name', 'updatedByUser:id,name', 'items.buch:id,buch_no,weight']);
    }

    private function formatVoucher(VacuumVoucher $voucher, bool $includeItems = true): array
    {
        $data = [
            'id' => (int) $voucher->id,
            'company_id' => (int) $voucher->company_id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'vacuum_process_id' => (int) $voucher->vacuum_process_id,
            'job_worker_id' => (int) $voucher->job_worker_id,
            'formula_value' => $this->decimalValue($voucher->formula_value, 3),
            'gross_wt_total' => $this->decimalValue($voucher->gross_wt_total, 3),
            'buch_wt_total' => $this->decimalValue($voucher->buch_wt_total, 3),
            'net_wt_total' => $this->decimalValue($voucher->net_wt_total, 3),
            'silver_wt_total' => $this->decimalValue($voucher->silver_wt_total, 3),
            'remarks' => $voucher->remarks,
            'created_by' => $voucher->created_by ? (int) $voucher->created_by : null,
            'updated_by' => $voucher->updated_by ? (int) $voucher->updated_by : null,
            'modified_count' => (int) $voucher->modified_count,
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($voucher->updated_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y h:i A'),
            'updated_at_view' => optional($voucher->updated_at)->format('d-m-Y h:i A'),
            'process' => $voucher->process ? [
                'id' => (int) $voucher->process->id,
                'name' => $voucher->process->name,
            ] : null,
            'job_worker' => $voucher->jobWorker ? [
                'id' => (int) $voucher->jobWorker->id,
                'name' => $voucher->jobWorker->name,
            ] : null,
            'created_by_user' => $voucher->createdByUser ? [
                'id' => (int) $voucher->createdByUser->id,
                'name' => $voucher->createdByUser->name,
            ] : null,
            'updated_by_user' => $voucher->updatedByUser ? [
                'id' => (int) $voucher->updatedByUser->id,
                'name' => $voucher->updatedByUser->name,
            ] : null,
        ];

        if ($includeItems && $voucher->relationLoaded('items')) {
            $data['items'] = $voucher->items->map(fn($item) => [
                'id' => (int) $item->id,
                'vacuum_voucher_id' => (int) $item->vacuum_voucher_id,
                'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                'buch_no' => $item->buch_no,
                'gross_wt' => $this->decimalValue($item->gross_wt, 3),
                'buch_wt' => $this->decimalValue($item->buch_wt, 3),
                'net_wt' => $this->decimalValue($item->net_wt, 3),
                'silver_wt' => $this->decimalValue($item->silver_wt, 3),
                'buch' => $item->relationLoaded('buch') && $item->buch ? [
                    'id' => (int) $item->buch->id,
                    'buch_no' => $item->buch->buch_no,
                    'weight' => $this->decimalValue($item->buch->weight, 3),
                ] : null,
            ])->values();
        }

        return $data;
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }

    private function usedBuchIds(int $companyId, ?int $ignoreVoucherId = null): array
    {
        return VacuumVoucherItem::whereHas('voucher', function ($query) use ($companyId, $ignoreVoucherId) {
            $query->where('company_id', $companyId)
                ->when($ignoreVoucherId, fn($q) => $q->where('id', '!=', $ignoreVoucherId));
        })
            ->whereNotExists(function ($query) {
                $query->selectRaw('1')
                    ->from('casting_heating_items')
                    ->whereColumn('casting_heating_items.vacuum_voucher_item_id', 'vacuum_voucher_items.id')
                    ->where('casting_heating_items.in_bhati', true);
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

    private function calculatedRowsAndTotals(array $items, float $formula, int $companyId): array
    {
        $rows = [];
        $grossTotal = 0;
        $buchTotal = 0;
        $netTotal = 0;
        $silverTotal = 0;

        $buchs = VacuumBuch::where('company_id', $companyId)
            ->whereIn('id', collect($items)->pluck('vacuum_buch_id')->filter()->all())
            ->get()
            ->keyBy('id');

        foreach ($items as $item) {
            $gross = round((float) ($item['gross_wt'] ?? 0), 3);
            $buchWt = round((float) ($item['buch_wt'] ?? 0), 3);
            $net = round($gross - $buchWt, 3);
            $silver = round($net * $formula, 3);
            $buch = $buchs->get((int) $item['vacuum_buch_id']);

            $rows[] = [
                'vacuum_buch_id' => (int) $item['vacuum_buch_id'],
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
}
