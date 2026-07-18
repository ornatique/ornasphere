<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingSortingItem;
use App\Models\Company;
use App\Models\Item;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CastingSortingApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $rows = $this->completedTreeReceiveQuery($companyId, $fromDate, $toDate)
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
            ->select('vacuum_vouchers.*')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('MAX(COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at))')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId);
            }, 'tree_receive_datetime')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('tree_cutting_receive_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                            ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                    });
            }, 'tree_receive_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_sorting_items')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_sorting_items.company_id', $companyId);
            }, 'sorting_count')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_sorting_items')
                    ->selectRaw('COALESCE(SUM(casting_sorting_items.weight), 0)')
                    ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_sorting_items.company_id', $companyId);
            }, 'sorting_weight_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_sorting_items')
                    ->selectRaw('COALESCE(SUM(casting_sorting_items.quantity), 0)')
                    ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_sorting_items.company_id', $companyId);
            }, 'sorting_quantity_total')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_sorting_items')
                    ->selectRaw('MAX(COALESCE(casting_sorting_items.sorted_at, casting_sorting_items.created_at))')
                    ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_sorting_items.company_id', $companyId);
            }, 'sorting_datetime')
            ->orderByDesc('tree_receive_datetime')
            ->orderByDesc('id')
            ->get()
            ->map(fn($row) => $this->formatListRow($row))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Casting Sorting voucher not found'], 404);
        }

        return response()->json(['success' => true, 'data' => $this->formatVoucher(...$data)]);
    }

    public function update(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Casting Sorting voucher not found'], 404);
        }

        [$voucher] = $data;
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'rows.*.weight' => ['nullable', 'numeric', 'min:0'],
            'rows.*.quantity' => ['nullable', 'integer', 'min:0'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
            'items.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        $rows = $validated['rows'] ?? $validated['items'] ?? [];

        DB::transaction(function () use ($request, $companyId, $voucher, $rows) {
            CastingSortingItem::where('company_id', $companyId)
                ->where('vacuum_voucher_id', $voucher->id)
                ->delete();

            foreach ($rows as $row) {
                $itemId = $row['item_id'] ?? null;
                $weight = $row['weight'] ?? null;
                $quantity = $row['quantity'] ?? null;

                if (($itemId === null || $itemId === '') && ($weight === null || $weight === '') && ($quantity === null || $quantity === '')) {
                    continue;
                }

                if ($itemId === null || $itemId === '') {
                    continue;
                }

                CastingSortingItem::create([
                    'company_id' => $companyId,
                    'vacuum_voucher_id' => $voucher->id,
                    'item_id' => (int) $itemId,
                    'weight' => $weight !== null && $weight !== '' ? (float) $weight : null,
                    'quantity' => $quantity !== null && $quantity !== '' ? (int) $quantity : null,
                    'sorted_by' => (int) $request->user()->id,
                    'sorted_at' => now(),
                ]);
            }
        });

        $updatedData = $this->voucherData($request, (int) $voucher->id);

        return response()->json([
            'success' => true,
            'message' => 'Casting Sorting updated successfully',
            'data' => $updatedData ? $this->formatVoucher(...$updatedData) : null,
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $data = $this->voucherData($request, (int) $id);
        if (!$data) {
            return response()->json(['success' => false, 'message' => 'Casting Sorting voucher not found'], 404);
        }

        [$voucher, $sortingItems, $items, $treeReceiveCount] = $data;
        $company = Company::findOrFail((int) $request->user()->company_id);

        return Pdf::loadView('company.casting_sorting.pdf.show', compact('company', 'voucher', 'sortingItems', 'items', 'treeReceiveCount'))
            ->setPaper('a4', 'portrait')
            ->download('casting_sorting_' . $voucher->voucher_no . '.pdf');
    }

    private function completedTreeReceiveQuery(int $companyId, ?string $fromDate = null, ?string $toDate = null)
    {
        return VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->whereExists(function ($query) use ($companyId, $fromDate, $toDate) {
                $query->selectRaw('1')
                    ->from('tree_cutting_receive_items')
                    ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_receive_items.company_id', $companyId)
                    ->where(function ($q) {
                        $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                            ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                    })
                    ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at)'), '>=', $fromDate))
                    ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at)'), '<=', $toDate));
            })
            ->whereNotExists(function ($query) use ($companyId) {
                $query->selectRaw('1')
                    ->from('tree_cutting_issue_items')
                    ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('tree_cutting_issue_items.company_id', $companyId)
                    ->whereNotNull('tree_cutting_issue_items.receive_tree_wt')
                    ->whereNotExists(function ($subQuery) use ($companyId) {
                        $subQuery->selectRaw('1')
                            ->from('tree_cutting_receive_items')
                            ->whereColumn('tree_cutting_receive_items.tree_cutting_issue_item_id', 'tree_cutting_issue_items.id')
                            ->where('tree_cutting_receive_items.company_id', $companyId)
                            ->where(function ($q) {
                                $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                                    ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                            });
                    });
            });
    }

    private function voucherData(Request $request, int $id): ?array
    {
        $companyId = (int) $request->user()->company_id;
        $voucher = VacuumVoucher::where('company_id', $companyId)
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->find($id);

        if (!$voucher) {
            return null;
        }

        $issueCount = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('receive_tree_wt')
            ->count();

        $receiveCount = TreeCuttingReceiveItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->whereNotNull('receive_pc_wt')
                    ->orWhereNotNull('receive_tree_bhuko');
            })
            ->count();

        if ($issueCount === 0 || $issueCount !== $receiveCount) {
            return null;
        }

        $sortingItems = CastingSortingItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('item:id,item_name,item_code')
            ->orderBy('id')
            ->get();

        $items = Item::where('company_id', $companyId)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'item_code']);

        return [$voucher, $sortingItems, $items, $receiveCount];
    }

    private function formatListRow(VacuumVoucher $row): array
    {
        $processDateTime = $row->sorting_datetime ?: $row->tree_receive_datetime;

        return [
            'id' => (int) $row->id,
            'voucher_no' => $row->voucher_no,
            'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
            'date_time' => $processDateTime ? \Carbon\Carbon::parse($processDateTime)->format('d-m-Y / h:i A') : null,
            'process_datetime' => $processDateTime ? \Carbon\Carbon::parse($processDateTime)->format('Y-m-d H:i:s') : null,
            'process_id' => (int) $row->vacuum_process_id,
            'process_name' => $row->process?->name,
            'worker_id' => (int) $row->job_worker_id,
            'worker_name' => $row->jobWorker?->name,
            'total_pcs' => (int) ($row->tree_receive_count ?? 0),
            'sorting_count' => (int) ($row->sorting_count ?? 0),
            'sorting_wt_total' => $this->decimalValue($row->sorting_weight_total, 3),
            'quantity_total' => (int) ($row->sorting_quantity_total ?? 0),
            'tree_receive_datetime' => $row->tree_receive_datetime ? \Carbon\Carbon::parse($row->tree_receive_datetime)->format('Y-m-d H:i:s') : null,
            'sorting_datetime' => $row->sorting_datetime ? \Carbon\Carbon::parse($row->sorting_datetime)->format('Y-m-d H:i:s') : null,
        ];
    }

    private function formatVoucher(VacuumVoucher $voucher, $sortingItems, $items, int $treeReceiveCount): array
    {
        $processDateTime = $this->latestProcessDateTime($sortingItems, 'sorted_at')
            ?: $voucher->created_at;

        $rows = $sortingItems->map(function ($sortingItem) {
            return [
                'id' => (int) $sortingItem->id,
                'item_id' => (int) $sortingItem->item_id,
                'item_name' => $sortingItem->item?->item_name,
                'item_code' => $sortingItem->item?->item_code,
                'weight' => $sortingItem->weight !== null ? $this->decimalValue($sortingItem->weight, 3) : null,
                'quantity' => $sortingItem->quantity !== null ? (int) $sortingItem->quantity : null,
                'sorted_by' => $sortingItem->sorted_by ? (int) $sortingItem->sorted_by : null,
                'sorted_at' => optional($sortingItem->sorted_at)->format('Y-m-d H:i:s'),
                'sorted_at_view' => optional($sortingItem->sorted_at)->format('d-m-Y / h:i A'),
            ];
        })->values()->all();

        while (count($rows) < 10) {
            $rows[] = $this->blankSortingRow();
        }

        $lastRow = $rows[count($rows) - 1] ?? [];
        $lastRowHasData = !empty($lastRow['item_id'])
            || !empty($lastRow['weight'])
            || !empty($lastRow['quantity']);

        if (count($rows) >= 10 && $lastRowHasData) {
            $rows[] = $this->blankSortingRow();
        }

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
            'total_pcs' => $treeReceiveCount,
            'sorting_count' => $sortingItems->count(),
            'sorting_wt_total' => $this->decimalValue($sortingItems->sum(fn($item) => (float) ($item->weight ?? 0)), 3),
            'quantity_total' => (int) $sortingItems->sum(fn($item) => (int) ($item->quantity ?? 0)),
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $rows,
            'item_options' => $items->map(fn($item) => [
                'id' => (int) $item->id,
                'item_name' => $item->item_name,
                'item_code' => $item->item_code,
                'name' => trim($item->item_name . ($item->item_code ? ' - ' . $item->item_code : '')),
            ])->values(),
        ];
    }

    private function blankSortingRow(): array
    {
        return [
            'id' => null,
            'item_id' => null,
            'item_name' => null,
            'item_code' => null,
            'weight' => null,
            'quantity' => null,
            'sorted_by' => null,
            'sorted_at' => null,
            'sorted_at_view' => null,
        ];
    }

    private function latestProcessDateTime($rows, string $preferredColumn)
    {
        return $rows
            ->map(fn($row) => $row->{$preferredColumn} ?: $row->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }
}
