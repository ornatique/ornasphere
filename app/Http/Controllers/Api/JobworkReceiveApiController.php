<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Item;
use App\Models\JobworkIssue;
use App\Models\JobworkReceive;
use App\Models\JobworkReceiveItem;
use App\Models\OtherCharge;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class JobworkReceiveApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = $this->baseQuery($request, $companyId)
            ->orderByDesc('jobwork_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($row) => $this->formatListRow($row))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = $this->findIssue($companyId, (int) $id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive voucher not found.'], 404);
        }

        $receive = $this->ensureReceive($request, $issue);

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher($issue, $receive),
        ]);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = $this->findIssue($companyId, (int) $id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive voucher not found.'], 404);
        }

        $validated = $request->validate([
            'receive_date' => ['required', 'date'],
            'remarks' => ['nullable', 'string'],
            'items' => ['nullable', 'array'],
            'items.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($query) => $query->where('company_id', $companyId)),
            ],
            'items.*.receive_gross_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_amt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_charge_details' => ['nullable'],
            'items.*.receive_net_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_fine_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.receive_qty_pcs' => ['nullable', 'integer', 'min:0'],
            'items.*.remarks' => ['nullable', 'string'],
        ]);

        $receive = DB::transaction(function () use ($request, $issue, $validated) {
            $receive = $this->ensureReceive($request, $issue);
            $receive->update([
                'receive_date' => $validated['receive_date'],
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);

            $receive->items()->delete();
            $this->createReceiveItems($receive, $issue, $validated['items'] ?? []);

            return $receive->load(['items.item:id,item_name', 'items.jobworkIssueItem.item:id,item_name']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Jobwork Receive updated successfully.',
            'data' => $this->formatVoucher($issue->fresh(['items.item:id,item_name', 'jobWorker:id,name', 'productionStep:id,name']), $receive),
        ]);
    }

    public function destroyItem(Request $request, $id, $itemId)
    {
        $companyId = (int) $request->user()->company_id;
        $issue = $this->findIssue($companyId, (int) $id);

        if (!$issue) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive voucher not found.'], 404);
        }

        $receive = JobworkReceive::where('company_id', $companyId)
            ->where('jobwork_issue_id', $issue->id)
            ->first();

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive not found.'], 404);
        }

        $item = JobworkReceiveItem::where('jobwork_receive_id', $receive->id)->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive item not found.'], 404);
        }

        DB::transaction(function () use ($receive, $item) {
            $item->delete();
            $receive->update([
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Jobwork Receive item deleted successfully.',
            'data' => [
                'jobwork_issue_id' => (int) $issue->id,
                'jobwork_receive_id' => (int) $receive->id,
                'deleted_item_id' => (int) $itemId,
            ],
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $row = $this->findIssue($companyId, (int) $id);

        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Jobwork Receive voucher not found.'], 404);
        }

        $receive = $this->ensureReceive($request, $row);
        $workerIssueVouchers = collect();

        return Pdf::loadView('company.jobwork_receive.pdf.show', compact('company', 'row', 'receive', 'workerIssueVouchers'))
            ->setPaper('a4', 'landscape')
            ->download('jobwork_receive_' . $row->voucher_no . '.pdf');
    }

    public function otherCharges(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        return response()->json([
            'success' => true,
            'data' => $this->otherChargeOptions($companyId),
        ]);
    }

    private function baseQuery(Request $request, int $companyId)
    {
        return JobworkIssue::query()
            ->where('company_id', $companyId)
            ->whereHas('items')
            ->with(['jobWorker:id,name', 'productionStep:id,name'])
            ->with([
                'receive' => fn($query) => $query
                    ->withCount([
                        'items as assigned_receive_count' => fn($itemQuery) => $itemQuery
                            ->where(fn($q) => $q->where('receive_net_wt', '>', 0)->orWhere('receive_qty_pcs', '>', 0)),
                    ])
                    ->withSum('items as receive_net_wt_sum', 'receive_net_wt')
                    ->withSum('items as loss_wt_sum', 'loss_wt'),
            ])
            ->withCount('items')
            ->withSum('items as issue_net_wt_sum', 'net_wt')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('jobwork_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('jobwork_date', '<=', $request->input('to_date')))
            ->when($request->filled('worker_id'), fn($q) => $q->where('job_worker_id', (int) $request->input('worker_id')))
            ->when($request->filled('process_id'), fn($q) => $q->where('production_step_id', (int) $request->input('process_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('jobWorker', fn($worker) => $worker->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('productionStep', fn($step) => $step->where('name', 'like', "%{$search}%"));
                });
            });
    }

    private function findIssue(int $companyId, int $id): ?JobworkIssue
    {
        return JobworkIssue::query()
            ->where('company_id', $companyId)
            ->whereHas('items')
            ->with(['jobWorker:id,name', 'productionStep:id,name', 'items.item:id,item_name'])
            ->withSum('items as issue_net_wt_sum', 'net_wt')
            ->find($id);
    }

    private function ensureReceive(Request $request, JobworkIssue $issue): JobworkReceive
    {
        return JobworkReceive::firstOrCreate(
            [
                'company_id' => (int) $request->user()->company_id,
                'jobwork_issue_id' => $issue->id,
            ],
            [
                'receive_date' => now()->toDateString(),
                'created_by' => (int) $request->user()->id,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => 0,
            ]
        )->load(['items.item:id,item_name', 'items.jobworkIssueItem.item:id,item_name']);
    }

    private function formatListRow(JobworkIssue $row): array
    {
        $issueNet = (float) ($row->issue_net_wt_sum ?? 0);
        $receiveNet = (float) ($row->receive?->receive_net_wt_sum ?? 0);
        $pendingNet = max(0, $issueNet - $receiveNet);
        $extraNet = max(0, $receiveNet - $issueNet);
        $assigned = (int) ($row->receive?->assigned_receive_count ?? 0);
        $total = (int) ($row->items_count ?? 0);

        return [
            'id' => (int) $row->id,
            'jobwork_issue_id' => (int) $row->id,
            'jobwork_receive_id' => $row->receive?->id ? (int) $row->receive->id : null,
            'voucher_no' => $row->voucher_no,
            'voucher_date' => optional($row->jobwork_date)->format('Y-m-d'),
            'voucher_date_view' => optional($row->jobwork_date)->format('d-m-Y'),
            'job_worker_id' => $row->job_worker_id ? (int) $row->job_worker_id : null,
            'jobworker_name' => $row->jobWorker?->name,
            'production_step_id' => $row->production_step_id ? (int) $row->production_step_id : null,
            'production_step_name' => $row->productionStep?->name,
            'issue_count' => $total,
            'assigned_receive' => $assigned,
            'pending_receive' => max(0, $total - $assigned),
            'issue_net_wt' => $this->decimalValue($issueNet, 3),
            'receive_net_wt' => $this->decimalValue($receiveNet, 3),
            'pending_net_wt' => $this->decimalValue($pendingNet, 3),
            'extra_net_wt' => $this->decimalValue($extraNet, 3),
            'loss_wt' => $this->decimalValue($row->receive?->loss_wt_sum, 3),
            'status' => $issueNet > 0 && $pendingNet <= 0.0005 ? 'completed' : ($receiveNet > 0 ? 'partial' : 'pending'),
        ];
    }

    private function formatVoucher(JobworkIssue $issue, JobworkReceive $receive): array
    {
        $issueItemOptions = $this->issueItemOptions($issue);
        $receiveRows = $receive->items
            ->filter(fn($item) => $item->item_id || $item->jobworkIssueItem)
            ->map(function ($item) {
                $itemId = $item->item_id ?: $item->jobworkIssueItem?->item_id;

                return [
                    'id' => (int) $item->id,
                    'jobwork_receive_item_id' => (int) $item->id,
                    'jobwork_issue_item_id' => $item->jobwork_issue_item_id ? (int) $item->jobwork_issue_item_id : null,
                    'item_id' => $itemId ? (int) $itemId : null,
                    'item_name' => $item->item?->item_name ?? $item->jobworkIssueItem?->item?->item_name,
                    'receive_gross_wt' => $this->decimalValue($item->receive_gross_wt, 3),
                    'other_wt' => $this->decimalValue($item->other_wt, 3),
                    'other_amt' => $this->decimalValue($item->other_amt, 2),
                    'other_charge_details' => $this->decodeOtherChargeDetails($item->other_charge_details),
                    'receive_net_wt' => $this->decimalValue($item->receive_net_wt, 3),
                    'receive_fine_wt' => $this->decimalValue($item->receive_fine_wt, 3),
                    'receive_qty_pcs' => (int) ($item->receive_qty_pcs ?? 0),
                    'loss_wt' => $this->decimalValue($item->loss_wt, 3),
                    'remarks' => $item->remarks,
                ];
            })
            ->values();

        $totalIssueNet = (float) ($issue->issue_net_wt_sum ?? $issue->items->sum('net_wt'));
        $totalReceiveNet = $receiveRows->sum(fn($item) => (float) $item['receive_net_wt']);

        return [
            'id' => (int) $issue->id,
            'jobwork_issue_id' => (int) $issue->id,
            'jobwork_receive_id' => (int) $receive->id,
            'voucher_no' => $issue->voucher_no,
            'voucher_date' => optional($issue->jobwork_date)->format('Y-m-d'),
            'receive_date' => optional($receive->receive_date)->format('Y-m-d'),
            'job_worker_id' => $issue->job_worker_id ? (int) $issue->job_worker_id : null,
            'jobworker_name' => $issue->jobWorker?->name,
            'production_step_id' => $issue->production_step_id ? (int) $issue->production_step_id : null,
            'production_step_name' => $issue->productionStep?->name,
            'issue_net_wt_total' => $this->decimalValue($totalIssueNet, 3),
            'receive_net_wt_total' => $this->decimalValue($totalReceiveNet, 3),
            'pending_net_wt_total' => $this->decimalValue(max(0, $totalIssueNet - $totalReceiveNet), 3),
            'extra_net_wt_total' => $this->decimalValue(max(0, $totalReceiveNet - $totalIssueNet), 3),
            'remarks' => $receive->remarks,
            'item_options' => $issueItemOptions,
            'other_charges' => $this->otherChargeOptions((int) $issue->company_id),
            'items' => $receiveRows,
        ];
    }

    private function issueItemOptions(JobworkIssue $issue)
    {
        $issueByItemId = $issue->items
            ->groupBy('item_id')
            ->map(function ($items) {
                $first = $items->first();
                return [
                    'item_id' => (int) $first->item_id,
                    'item_name' => $first->item?->item_name ?? '-',
                    'issue_net_wt' => $this->decimalValue($items->sum('net_wt'), 3),
                    'issue_qty' => (int) $items->sum('qty_pcs'),
                    'purity' => $this->decimalValue($first->net_purity ?: $first->purity ?: 0, 3),
                ];
            });

        return Item::where('company_id', $issue->company_id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'outward_purity'])
            ->map(function ($item) use ($issueByItemId) {
                $issue = $issueByItemId->get($item->id);
                return [
                    'id' => (int) $item->id,
                    'item_id' => (int) $item->id,
                    'item_name' => $item->item_name,
                    'issue_net_wt' => $this->decimalValue($issue['issue_net_wt'] ?? 0, 3),
                    'issue_qty' => (int) ($issue['issue_qty'] ?? 0),
                    'purity' => $this->decimalValue($issue['purity'] ?? $item->outward_purity ?? 0, 3),
                ];
            })
            ->values();
    }

    private function otherChargeOptions(int $companyId)
    {
        return OtherCharge::where('company_id', $companyId)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id')
            ->get()
            ->map(fn($charge) => [
                'id' => (int) $charge->id,
                'name' => $charge->other_charge,
                'code' => $charge->code,
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

    private function normalizeOtherChargeDetails($value): ?string
    {
        if ($value === null || $value === '' || $value === []) {
            return null;
        }

        if (is_array($value)) {
            return json_encode(array_values($value));
        }

        return (string) $value;
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
                'other_charge_details' => $this->normalizeOtherChargeDetails($item['other_charge_details'] ?? null),
                'receive_net_wt' => $prepared['receive_net_wt'],
                'receive_fine_wt' => (float) ($item['receive_fine_wt'] ?? 0),
                'receive_qty_pcs' => $prepared['receive_qty_pcs'],
                'loss_wt' => $loss,
                'remarks' => $prepared['remarks'],
            ]);
        }
    }

    private function decodeOtherChargeDetails(?string $value): array
    {
        if (!$value) {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function decimalValue($value, int $precision): string
    {
        return number_format((float) ($value ?? 0), $precision, '.', '');
    }
}
