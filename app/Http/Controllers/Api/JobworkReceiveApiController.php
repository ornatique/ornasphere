<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Customer;
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
        $rows = $this->listRows($request, $companyId);

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
            ->setPaper('a4', 'portrait')
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

    public function directIndex(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = $this->directBaseQuery($request, $companyId)
            ->orderByDesc('receive_date')
            ->orderByDesc('id')
            ->get()
            ->map(fn($row) => $this->formatDirectListRow($row))
            ->values();

        return response()->json(['success' => true, 'data' => $rows]);
    }

    public function directOptions(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        return response()->json([
            'success' => true,
            'data' => [
                'customers' => Customer::where('company_id', $companyId)
                    ->orderBy('name')
                    ->get(['id', 'name', 'mobile_no'])
                    ->map(fn($customer) => [
                        'id' => (int) $customer->id,
                        'name' => $customer->name,
                        'mobile_no' => $customer->mobile_no,
                    ])
                    ->values(),
                'items' => Item::where('company_id', $companyId)
                    ->orderBy('item_name')
                    ->get(['id', 'item_name', 'item_code', 'outward_purity', 'inward_purity', 'labour_rate'])
                    ->map(fn($item) => [
                        'id' => (int) $item->id,
                        'item_id' => (int) $item->id,
                        'item_name' => $item->item_name,
                        'item_code' => $item->item_code,
                        'purity' => $this->decimalValue((float) ($item->outward_purity ?: $item->inward_purity ?: 0), 3),
                        'labour_rate' => $this->decimalValue($item->labour_rate, 2),
                    ])
                    ->values(),
                'other_charges' => $this->otherChargeOptions($companyId),
            ],
        ]);
    }

    public function directStore(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $validated = $this->validateDirectReceiveData($request, $companyId);

        $receive = DB::transaction(function () use ($request, $validated, $companyId) {
            $receive = JobworkReceive::create([
                'company_id' => $companyId,
                'jobwork_issue_id' => null,
                'receive_no' => $this->generateDirectReceiveNo($companyId, $validated['receive_date']),
                'receive_date' => $validated['receive_date'],
                'job_worker_id' => null,
                'customer_id' => $validated['customer_id'],
                'production_step_id' => null,
                'receive_type' => 'direct',
                'remarks' => $validated['remarks'] ?? null,
                'created_by' => (int) $request->user()->id,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => 0,
            ]);

            $this->syncDirectReceiveItems($receive, $validated['items']);

            return $receive->load(['customer:id,name', 'items.item:id,item_name,item_code']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct Jobwork Receive created successfully.',
            'data' => $this->formatDirectVoucher($receive),
        ], 200);
    }

    public function directShow(Request $request, $id)
    {
        $receive = $this->findDirectReceive((int) $request->user()->company_id, (int) $id);

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive voucher not found.'], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatDirectVoucher($receive),
        ]);
    }

    public function directUpdate(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $receive = $this->findDirectReceive($companyId, (int) $id);

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive voucher not found.'], 404);
        }

        $validated = $this->validateDirectReceiveData($request, $companyId);

        $receive = DB::transaction(function () use ($request, $receive, $validated) {
            $receive->update([
                'receive_date' => $validated['receive_date'],
                'job_worker_id' => null,
                'customer_id' => $validated['customer_id'],
                'production_step_id' => null,
                'remarks' => $validated['remarks'] ?? null,
                'updated_by' => (int) $request->user()->id,
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);

            $this->syncDirectReceiveItems($receive, $validated['items']);

            return $receive->fresh(['customer:id,name', 'items.item:id,item_name,item_code']);
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct Jobwork Receive updated successfully.',
            'data' => $this->formatDirectVoucher($receive),
        ]);
    }

    public function directDestroyItem(Request $request, $id, $itemId)
    {
        $receive = $this->findDirectReceive((int) $request->user()->company_id, (int) $id);

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive voucher not found.'], 404);
        }

        $item = JobworkReceiveItem::where('jobwork_receive_id', $receive->id)->find($itemId);
        if (!$item) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive item not found.'], 404);
        }

        DB::transaction(function () use ($receive, $item) {
            $item->delete();
            $receive->update([
                'modified_count' => ((int) $receive->modified_count) + 1,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct Jobwork Receive item deleted successfully.',
            'data' => [
                'jobwork_receive_id' => (int) $receive->id,
                'deleted_item_id' => (int) $itemId,
            ],
        ]);
    }

    public function directDestroy(Request $request, $id)
    {
        $receive = $this->findDirectReceive((int) $request->user()->company_id, (int) $id);

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive voucher not found.'], 404);
        }

        DB::transaction(function () use ($receive) {
            $receive->items()->delete();
            $receive->delete();
        });

        return response()->json([
            'success' => true,
            'message' => 'Direct Jobwork Receive deleted successfully.',
        ]);
    }

    public function directPdf(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::findOrFail($companyId);
        $receive = $this->findDirectReceive($companyId, (int) $id);

        if (!$receive) {
            return response()->json(['success' => false, 'message' => 'Direct Jobwork Receive voucher not found.'], 404);
        }

        return Pdf::loadView('company.jobwork_receive.pdf.direct', compact('company', 'receive'))
            ->setPaper('a4', 'portrait')
            ->download('direct_jobwork_receive_' . ($receive->receive_no ?: $receive->id) . '.pdf');
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

    private function directBaseQuery(Request $request, int $companyId)
    {
        $search = trim((string) ($request->input('search') ?: $request->input('search_text', '')));

        return JobworkReceive::query()
            ->where('company_id', $companyId)
            ->where('receive_type', 'direct')
            ->with(['customer:id,name', 'items.item:id,item_name,item_code'])
            ->withSum('items as receive_gross_wt_sum', 'receive_gross_wt')
            ->withSum('items as other_wt_sum', 'other_wt')
            ->withSum('items as receive_net_wt_sum', 'receive_net_wt')
            ->withSum('items as receive_fine_wt_sum', 'receive_fine_wt')
            ->withSum('items as total_amount_sum', 'total_amount')
            ->when($request->filled('from_date'), fn($q) => $q->whereDate('receive_date', '>=', $request->input('from_date')))
            ->when($request->filled('to_date'), fn($q) => $q->whereDate('receive_date', '<=', $request->input('to_date')))
            ->when($request->filled('customer_id'), fn($q) => $q->where('customer_id', (int) $request->input('customer_id')))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('receive_no', 'like', "%{$search}%")
                        ->orWhereHas('customer', fn($customer) => $customer->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('items.item', function ($item) use ($search) {
                            $item->where('item_name', 'like', "%{$search}%")
                                ->orWhere('item_code', 'like', "%{$search}%");
                        });
                });
            });
    }

    private function listRows(Request $request, int $companyId)
    {
        $status = (string) $request->input('status', '');
        $rows = collect();

        if ($status !== 'direct') {
            $issueRows = $this->baseQuery($request, $companyId)
                ->get()
                ->map(fn($row) => [
                    'sort_at' => optional($row->jobwork_date)->timestamp ?? optional($row->created_at)->timestamp ?? 0,
                    'data' => $this->formatListRow($row),
                ])
                ->when(in_array($status, ['pending', 'partial', 'completed'], true), function ($collection) use ($status) {
                    return $collection->filter(fn($row) => ($row['data']['status'] ?? '') === $status);
                });

            $rows = $rows->merge($issueRows);
        }

        if ($status === '' || $status === 'direct') {
            $directRows = $this->directBaseQuery($request, $companyId)
                ->get()
                ->map(fn($row) => [
                    'sort_at' => optional($row->receive_date)->timestamp ?? optional($row->created_at)->timestamp ?? 0,
                    'data' => $this->formatDirectListRow($row),
                ]);

            $rows = $rows->merge($directRows);
        }

        return $rows
            ->sortByDesc('sort_at')
            ->pluck('data')
            ->values();
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
                $issuePurity = (float) ($first->net_purity ?: $first->purity ?: 0);
                $itemPurity = (float) ($first->item?->outward_purity ?: $first->item?->inward_purity ?: 0);
                $purity = $issuePurity > 0 ? $issuePurity : $itemPurity;

                return [
                    'item_id' => (int) $first->item_id,
                    'item_name' => $first->item?->item_name ?? '-',
                    'issue_net_wt' => $this->decimalValue($items->sum('net_wt'), 3),
                    'issue_qty' => (int) $items->sum('qty_pcs'),
                    'purity' => $this->decimalValue($purity, 3),
                ];
            });

        return Item::where('company_id', $issue->company_id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'outward_purity', 'inward_purity'])
            ->map(function ($item) use ($issueByItemId) {
                $issue = $issueByItemId->get($item->id);
                $issuePurity = (float) ($issue['purity'] ?? 0);
                $itemPurity = (float) ($item->outward_purity ?: $item->inward_purity ?: 0);
                $purity = $issuePurity > 0 ? $issuePurity : $itemPurity;

                return [
                    'id' => (int) $item->id,
                    'item_id' => (int) $item->id,
                    'item_name' => $item->item_name,
                    'issue_net_wt' => $this->decimalValue($issue['issue_net_wt'] ?? 0, 3),
                    'issue_qty' => (int) ($issue['issue_qty'] ?? 0),
                    'purity' => $this->decimalValue($purity, 3),
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

    private function findDirectReceive(int $companyId, int $id): ?JobworkReceive
    {
        return JobworkReceive::query()
            ->where('company_id', $companyId)
            ->where('receive_type', 'direct')
            ->with(['customer:id,name', 'items.item:id,item_name,item_code'])
            ->find($id);
    }

    private function validateDirectReceiveData(Request $request, int $companyId): array
    {
        return $request->validate([
            'receive_date' => ['required', 'date'],
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
            'remarks' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_id' => ['required', 'integer', Rule::exists('items', 'id')->where(fn($query) => $query->where('company_id', $companyId))],
            'items.*.receive_gross_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_wt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_amt' => ['nullable', 'numeric', 'min:0'],
            'items.*.other_charge_details' => ['nullable'],
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
                'other_charge_details' => $this->normalizeOtherChargeDetails($item['other_charge_details'] ?? null),
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

    private function formatDirectListRow(JobworkReceive $row): array
    {
        return [
            'id' => (int) $row->id,
            'jobwork_issue_id' => null,
            'jobwork_receive_id' => (int) $row->id,
            'receive_no' => $row->receive_no,
            'voucher_no' => $row->receive_no,
            'receive_date' => optional($row->receive_date)->format('Y-m-d'),
            'receive_date_view' => optional($row->receive_date)->format('d-m-Y'),
            'voucher_date' => optional($row->receive_date)->format('Y-m-d'),
            'voucher_date_view' => optional($row->receive_date)->format('d-m-Y'),
            'customer_id' => $row->customer_id ? (int) $row->customer_id : null,
            'customer_name' => $row->customer?->name,
            'job_worker_id' => null,
            'jobworker_name' => $row->customer?->name,
            'party_name' => $row->customer?->name,
            'production_step_id' => null,
            'production_step_name' => null,
            'issue_count' => (int) $row->items->count(),
            'assigned_receive' => (int) $row->items->count(),
            'pending_receive' => 0,
            'receive_type' => 'direct',
            'issue_net_wt' => '0.000',
            'receive_gross_wt' => $this->decimalValue($row->receive_gross_wt_sum, 3),
            'other_wt' => $this->decimalValue($row->other_wt_sum, 3),
            'receive_net_wt' => $this->decimalValue($row->receive_net_wt_sum, 3),
            'pending_net_wt' => '0.000',
            'extra_net_wt' => '0.000',
            'loss_wt' => '0.000',
            'receive_fine_wt' => $this->decimalValue($row->receive_fine_wt_sum, 3),
            'total_amount' => $this->decimalValue($row->total_amount_sum, 2),
            'status' => 'direct',
            'remarks' => $row->remarks,
        ];
    }

    private function formatDirectVoucher(JobworkReceive $receive): array
    {
        $items = $receive->items
            ->map(fn($item) => [
                'id' => (int) $item->id,
                'jobwork_receive_item_id' => (int) $item->id,
                'item_id' => $item->item_id ? (int) $item->item_id : null,
                'item_name' => $item->item?->item_name,
                'item_code' => $item->item?->item_code,
                'receive_gross_wt' => $this->decimalValue($item->receive_gross_wt, 3),
                'other_wt' => $this->decimalValue($item->other_wt, 3),
                'other_amt' => $this->decimalValue($item->other_amt, 2),
                'other_charge_details' => $this->decodeOtherChargeDetails($item->other_charge_details),
                'purity' => $this->decimalValue($item->purity, 3),
                'waste_percent' => $this->decimalValue($item->waste_percent, 3),
                'net_purity' => $this->decimalValue($item->net_purity, 3),
                'receive_net_wt' => $this->decimalValue($item->receive_net_wt, 3),
                'receive_fine_wt' => $this->decimalValue($item->receive_fine_wt, 3),
                'metal_rate' => $this->decimalValue($item->metal_rate, 2),
                'metal_amount' => $this->decimalValue($item->metal_amount, 2),
                'labour_rate' => $this->decimalValue($item->labour_rate, 2),
                'labour_amount' => $this->decimalValue($item->labour_amount, 2),
                'receive_qty_pcs' => (int) ($item->receive_qty_pcs ?? 0),
                'loss_wt' => $this->decimalValue($item->loss_wt, 3),
                'remarks' => $item->remarks,
                'total_amount' => $this->decimalValue($item->total_amount, 2),
            ])
            ->values();

        return [
            'id' => (int) $receive->id,
            'jobwork_receive_id' => (int) $receive->id,
            'receive_no' => $receive->receive_no,
            'voucher_no' => $receive->receive_no,
            'receive_date' => optional($receive->receive_date)->format('Y-m-d'),
            'receive_date_view' => optional($receive->receive_date)->format('d-m-Y'),
            'receive_datetime_view' => optional($receive->created_at)->format('d-m-Y / h:i A'),
            'customer_id' => $receive->customer_id ? (int) $receive->customer_id : null,
            'customer_name' => $receive->customer?->name,
            'job_worker_id' => null,
            'jobworker_name' => $receive->customer?->name,
            'party_name' => $receive->customer?->name,
            'production_step_id' => null,
            'production_step_name' => null,
            'receive_type' => 'direct',
            'status' => 'direct',
            'qty_total' => (int) $items->sum('receive_qty_pcs'),
            'receive_gross_wt_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['receive_gross_wt']), 3),
            'other_wt_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['other_wt']), 3),
            'receive_net_wt_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['receive_net_wt']), 3),
            'receive_fine_wt_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['receive_fine_wt']), 3),
            'metal_amount_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['metal_amount']), 2),
            'labour_amount_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['labour_amount']), 2),
            'other_amt_total' => $this->decimalValue($items->sum(fn($item) => (float) $item['other_amt']), 2),
            'total_amount' => $this->decimalValue($items->sum(fn($item) => (float) $item['total_amount']), 2),
            'remarks' => $receive->remarks,
            'item_options' => Item::where('company_id', $receive->company_id)
                ->orderBy('item_name')
                ->get(['id', 'item_name', 'item_code', 'outward_purity', 'inward_purity', 'labour_rate'])
                ->map(fn($item) => [
                    'id' => (int) $item->id,
                    'item_id' => (int) $item->id,
                    'item_name' => $item->item_name,
                    'item_code' => $item->item_code,
                    'purity' => $this->decimalValue((float) ($item->outward_purity ?: $item->inward_purity ?: 0), 3),
                    'labour_rate' => $this->decimalValue($item->labour_rate, 2),
                ])
                ->values(),
            'other_charges' => $this->otherChargeOptions((int) $receive->company_id),
            'items' => $items,
        ];
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
