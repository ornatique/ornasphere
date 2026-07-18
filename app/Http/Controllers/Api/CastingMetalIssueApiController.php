<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\CastingMetalIssueItem;
use App\Models\Company;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CastingMetalIssueApiController extends Controller
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
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->withCount('items')
            ->select('vacuum_vouchers.*')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_metal_issue_items')
                    ->selectRaw('MAX(COALESCE(casting_metal_issue_items.issued_at, casting_metal_issue_items.created_at))')
                    ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_metal_issue_items.company_id', $companyId);
            }, 'metal_issue_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_metal_issue_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_metal_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_metal_issue_items.company_id', $companyId)
                    ->whereNotNull('casting_metal_issue_items.issue_silver_wt');
            }, 'assigned_metal_count')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($row) {
                $total = (int) ($row->items_count ?? 0);
                $assigned = (int) ($row->assigned_metal_count ?? 0);

                return [
                    'id' => (int) $row->id,
                    'voucher_no' => $row->voucher_no,
                    'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
                    'date_time' => $row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('d-m-Y / h:i A') : optional($row->created_at)->format('d-m-Y / h:i A'),
                    'process_datetime' => $row->metal_issue_datetime ? \Carbon\Carbon::parse($row->metal_issue_datetime)->format('Y-m-d H:i:s') : optional($row->created_at)->format('Y-m-d H:i:s'),
                    'process_id' => (int) $row->vacuum_process_id,
                    'process_name' => $row->process?->name,
                    'worker_id' => (int) $row->job_worker_id,
                    'worker_name' => $row->jobWorker?->name,
                    'total_pcs' => $total,
                    'assigned_metal' => $assigned,
                    'pending_metal' => max($total - $assigned, 0),
                    'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
                    'created_at_view' => optional($row->created_at)->format('d-m-Y / h:i A'),
                ];
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Metal Issue voucher not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher($voucher),
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)
            ->with('items:id,vacuum_voucher_id,silver_wt')
            ->find((int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Metal Issue voucher not found',
            ], 404);
        }

        $voucherItems = $voucher->items->keyBy('id');
        $validItemIds = $voucherItems->keys()->map(fn($itemId) => (int) $itemId)->all();
        $validator = Validator::make($request->all(), [
            'melting' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items' => ['nullable', 'array'],
            'items.*.issue_silver_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.is_if' => ['nullable', 'boolean'],
            'items.*.pure_fine' => ['nullable', 'numeric', 'min:0'],
            'items.*.if_percentage' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'items.*.other_metal' => ['nullable', 'numeric'],
            'items.*.remarks' => ['nullable', 'string', 'max:1000'],
        ]);

        $validator->after(function ($validator) use ($request) {
            $melting = $request->input('melting');
            foreach ((array) $request->input('items', []) as $itemId => $row) {
                $isIf = filter_var($row['is_if'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $ifPercentage = $melting !== null && $melting !== '' ? $melting : ($row['if_percentage'] ?? null);

                if ($isIf && ($ifPercentage === null || $ifPercentage === '' || (float) $ifPercentage <= 0)) {
                    $validator->errors()->add(
                        'melting',
                        'The Melting field must be greater than 0 when I/F is checked.'
                    );
                }
            }
        });

        $validated = $validator->validate();

        DB::transaction(function () use ($request, $companyId, $voucher, $voucherItems, $validItemIds, $validated) {
            $melting = $validated['melting'] ?? null;
            foreach (($validated['items'] ?? []) as $itemId => $row) {
                $itemId = (int) $itemId;

                if (!in_array($itemId, $validItemIds, true)) {
                    continue;
                }

                $issueSilverWt = $row['issue_silver_wt'] ?? null;
                $isIf = (bool) ($row['is_if'] ?? false);
                $voucherItem = $voucherItems->get($itemId);
                $ifPercentage = $melting !== null && $melting !== '' ? $melting : ($row['if_percentage'] ?? null);
                $ifPercentageValue = $isIf && $ifPercentage !== null && $ifPercentage !== '' ? (float) $ifPercentage : null;
                $pureFine = $row['pure_fine'] ?? null;
                $otherMetalInput = $row['other_metal'] ?? null;
                $pureFineValue = $isIf && $pureFine !== null && $pureFine !== ''
                    ? round((float) $pureFine, 3)
                    : ($isIf && $ifPercentageValue !== null
                        ? round(((float) ($voucherItem?->silver_wt ?? 0)) * ($ifPercentageValue / 100), 3)
                        : null);
                $otherMetal = $isIf && $otherMetalInput !== null && $otherMetalInput !== ''
                    ? round((float) $otherMetalInput, 3)
                    : null;
                $metalWeight = $isIf && $pureFineValue !== null && $otherMetal !== null
                    ? round($pureFineValue + $otherMetal, 3)
                    : ($isIf && $pureFineValue !== null && $ifPercentageValue > 0
                        ? round($pureFineValue / ($ifPercentageValue / 100), 3)
                        : null);
                $otherMetal = $otherMetal !== null
                    ? $otherMetal
                    : ($metalWeight !== null && $pureFineValue !== null
                        ? round($metalWeight - $pureFineValue, 3)
                        : null);
                $remarks = trim((string) ($row['remarks'] ?? ''));

                CastingMetalIssueItem::updateOrCreate(
                    [
                        'company_id' => $companyId,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'issue_silver_wt' => $issueSilverWt !== null && $issueSilverWt !== '' ? (float) $issueSilverWt : null,
                        'is_if' => $isIf,
                        'pure_fine' => $pureFineValue,
                        'if_percentage' => $ifPercentageValue,
                        'other_metal' => $otherMetal,
                        'metal_weight' => $metalWeight,
                        'remarks' => $remarks !== '' ? $remarks : null,
                        'issued_by' => (int) $request->user()->id,
                        'issued_at' => now(),
                    ]
                );
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Casting Metal Issue updated successfully',
            'data' => $this->formatVoucher($this->findVoucher($request, (int) $voucher->id)),
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Metal Issue voucher not found',
            ], 404);
        }

        $company = Company::findOrFail((int) $request->user()->company_id);
        $heatingItems = $voucher->heatingItems->keyBy('vacuum_voucher_item_id');
        $issueItems = $voucher->metalIssueItems->keyBy('vacuum_voucher_item_id');

        return Pdf::loadView('company.casting_metal_issue.pdf.show', compact('company', 'voucher', 'heatingItems', 'issueItems'))
            ->setPaper('a4', 'landscape')
            ->download('casting_metal_issue_' . $voucher->voucher_no . '.pdf');
    }

    private function findVoucher(Request $request, int $id): ?VacuumVoucher
    {
        return VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items', 'heatingItems', 'metalIssueItems'])
            ->withCount('items')
            ->first();
    }

    private function formatVoucher(VacuumVoucher $voucher): array
    {
        $heatingItems = $voucher->heatingItems->keyBy('vacuum_voucher_item_id');
        $issueItems = $voucher->metalIssueItems->keyBy('vacuum_voucher_item_id');
        $assigned = $issueItems->filter(fn($item) => $item->issue_silver_wt !== null)->count();
        $total = (int) ($voucher->items_count ?? $voucher->items->count());
        $processDateTime = $this->latestProcessDateTime($voucher->metalIssueItems, 'issued_at') ?: $voucher->created_at;

        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'date_time' => optional($processDateTime)->format('d-m-Y / h:i A'),
            'process_datetime' => optional($processDateTime)->format('Y-m-d H:i:s'),
            'process_id' => (int) $voucher->vacuum_process_id,
            'process_name' => $voucher->process?->name,
            'worker_id' => (int) $voucher->job_worker_id,
            'worker_name' => $voucher->jobWorker?->name,
            'total_pcs' => $total,
            'assigned_metal' => $assigned,
            'pending_metal' => max($total - $assigned, 0),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $voucher->items->map(function ($item) use ($heatingItems, $issueItems) {
                $heatingItem = $heatingItems->get($item->id);
                $issueItem = $issueItems->get($item->id);

                return [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->id,
                    'vacuum_buch_id' => $item->vacuum_buch_id ? (int) $item->vacuum_buch_id : null,
                    'buch_no' => $item->buch_no,
                    'silver_wt' => $this->decimalValue($item->silver_wt, 3),
                    'in_bhati' => (bool) ($heatingItem?->in_bhati ?? false),
                    'in_bhati_at' => optional($heatingItem?->checked_at)->format('Y-m-d H:i:s'),
                    'issue_silver_wt' => $issueItem?->issue_silver_wt !== null ? $this->decimalValue($issueItem->issue_silver_wt, 3) : null,
                    'is_if' => (bool) ($issueItem?->is_if ?? false),
                    'pure_fine' => $issueItem?->pure_fine !== null ? $this->decimalValue($issueItem->pure_fine, 3) : null,
                    'if_percentage' => $issueItem?->if_percentage !== null ? $this->decimalValue($issueItem->if_percentage, 2) : null,
                    'other_metal' => $issueItem?->other_metal !== null ? $this->decimalValue($issueItem->other_metal, 3) : null,
                    'metal_weight' => $issueItem?->metal_weight !== null ? $this->decimalValue($issueItem->metal_weight, 3) : null,
                    'remarks' => $issueItem?->remarks,
                    'issued_by' => $issueItem?->issued_by ? (int) $issueItem->issued_by : null,
                    'issued_at' => optional($issueItem?->issued_at)->format('Y-m-d H:i:s'),
                    'issued_at_view' => optional($issueItem?->issued_at)->format('d-m-Y / h:i A'),
                ];
            })->values(),
        ];
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }

    private function latestProcessDateTime($rows, string $preferredColumn)
    {
        return $rows
            ->map(fn($row) => $row->{$preferredColumn} ?: $row->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }
}
