<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ItemSet;
use App\Models\Customer;
use App\Models\Company;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use App\Models\Item;
use App\Models\SalePayment;
use App\Models\CustomerAdvanceLedger;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;

class SaleController extends Controller
{

    /**
     * Display listing (Yajra DataTable)
     */


    public function index(Request $request, $slug)
    {
        // ✅ Get Company
        $company = Company::where('slug', $slug)->firstOrFail();

        // ✅ AJAX (DataTable)
        if ($request->ajax()) {

            $query = Sale::with(['customer', 'creator'])
                ->withSum('saleItems as sum_qty', 'qty')
                ->withSum('saleItems as sum_gross_weight', 'gross_weight')
                ->withSum('saleItems as sum_net_weight', 'net_weight')
                ->withSum('saleItems as sum_fine_weight', 'fine_weight')
                ->withSum('saleItems as sum_metal_amount', 'metal_amount')
                ->withSum('saleItems as sum_labour_amount', 'labour_amount')
                ->withSum('saleItems as sum_other_amount', 'other_amount')
                ->where('company_id', $company->id);

            if ($request->filled('customer_id')) {
                $query->where('customer_id', (int) $request->customer_id);
            }

            // ✅ DATE FILTER (IMPORTANT)
            if (!empty($request->from_date) && !empty($request->to_date)) {

                $from = Carbon::parse($request->from_date)->startOfDay();
                $to   = Carbon::parse($request->to_date)->endOfDay();

                $query->whereBetween('sale_date', [$from, $to]);
            }

            // Optional: single date filter
            if (!empty($request->from_date) && empty($request->to_date)) {
                $query->whereDate('sale_date', '>=', $request->from_date);
            }

            if (empty($request->from_date) && !empty($request->to_date)) {
                $query->whereDate('sale_date', '<=', $request->to_date);
            }

            $query->orderByDesc('id');

            return DataTables::of($query)

                ->addIndexColumn()

                // ✅ CUSTOMER NAME
                ->addColumn('customer_name', function ($sale) {
                    return optional($sale->customer)->name ?? '-';
                })

                // ✅ DATE FORMAT
                ->editColumn('sale_date', function ($sale) {
                    return $sale->sale_date
                        ? Carbon::parse($sale->sale_date)->format('d-m-Y')
                        : '-';
                })

                // ✅ TOTAL FORMAT
                ->editColumn('net_total', function ($sale) {
                    return number_format($sale->net_total, 2);
                })
                ->addColumn('total_qty', fn($sale) => (int) ($sale->sum_qty ?? 0))
                ->addColumn('total_gross_weight', fn($sale) => number_format((float) ($sale->sum_gross_weight ?? 0), 3))
                ->addColumn('total_net_weight', fn($sale) => number_format((float) ($sale->sum_net_weight ?? 0), 3))
                ->addColumn('total_fine_weight', fn($sale) => number_format((float) ($sale->sum_fine_weight ?? 0), 3))
                ->addColumn('total_metal_amount', fn($sale) => number_format((float) ($sale->sum_metal_amount ?? 0), 2))
                ->addColumn('total_labour_amount', fn($sale) => number_format((float) ($sale->sum_labour_amount ?? 0), 2))
                ->addColumn('total_other_amount', fn($sale) => number_format((float) ($sale->sum_other_amount ?? 0), 2))
                ->addColumn('received_amount_fmt', fn($sale) => number_format((float) ($sale->received_amount ?? 0), 2))
                ->addColumn('refund_paid_amount_fmt', fn($sale) => number_format((float) ($sale->paid_amount ?? 0), 2))
                ->addColumn('pending_amount_fmt', function ($sale) {
                    $net = (float) ($sale->net_total ?? 0);
                    $effectiveReceived = (float) ($sale->received_amount ?? 0) - (float) ($sale->paid_amount ?? 0);
                    return number_format(max(0, $net - $effectiveReceived), 2);
                })
                ->addColumn('creator_name', fn($sale) => optional($sale->creator)->name ?? '-')
                ->addColumn('modified_at', function ($sale) {
                    return $sale->updated_at ? Carbon::parse($sale->updated_at)->format('d-m-Y h:i A') : '-';
                })
                ->addColumn('modified_count', fn($sale) => (int) ($sale->modified_count ?? 0))

                // ✅ ACTION BUTTON (VIEW PDF)
                ->addColumn('action', function ($sale) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $sale->id);

                    $pdfUrl = route('company.sales.pdf', [
                        'slug' => $company->slug,
                        'encryptedId' => $encryptedId
                    ]);

                    $editUrl = route('company.sales.edit', [
                        'slug' => $company->slug,
                        'encryptedId' => $encryptedId
                    ]);
                    $editBtn = '
                    <a href="' . $editUrl . '" 
                       class="btn btn-sm btn-warning me-1">
                        Edit
                    </a>
                    ';

                    return $editBtn . '
                    <a href="' . $pdfUrl . '" 
                       target="_blank"
                       class="btn btn-sm btn-info">
                        View
                    </a>
                ';
                })

                ->rawColumns(['action'])

                ->make(true);
        }

        // ✅ NORMAL VIEW
        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.sales.index', compact('company', 'customers'));
    }

    public function exportListPdf(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        [$fromDate, $toDate] = $this->resolveSaleDateRange($request);

        $rows = $this->saleListQuery($company->id, $request, $fromDate, $toDate)
            ->get()
            ->map(fn(Sale $sale) => $this->summarizeSaleListRow($sale))
            ->values();

        $pdf = Pdf::loadView('company.sales.list_pdf', compact('company', 'rows', 'fromDate', 'toDate'))
            ->setPaper('a4', 'landscape');

        return $pdf->stream('sales-list-' . now()->format('YmdHis') . '.pdf');
    }

    private function resolveSaleDateRange(Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $today = now()->toDateString();

        if (empty($fromDate) && empty($toDate)) {
            return [$today, $today];
        }

        return [$fromDate ?: $toDate, $toDate ?: $fromDate];
    }

    private function saleListQuery(int $companyId, Request $request, string $fromDate, string $toDate)
    {
        $query = Sale::with(['customer', 'creator'])
            ->withSum('saleItems as sum_qty', 'qty')
            ->withSum('saleItems as sum_gross_weight', 'gross_weight')
            ->withSum('saleItems as sum_net_weight', 'net_weight')
            ->withSum('saleItems as sum_fine_weight', 'fine_weight')
            ->withSum('saleItems as sum_metal_amount', 'metal_amount')
            ->withSum('saleItems as sum_labour_amount', 'labour_amount')
            ->withSum('saleItems as sum_other_amount', 'other_amount')
            ->where('company_id', $companyId)
            ->whereBetween('sale_date', [
                Carbon::parse($fromDate)->startOfDay(),
                Carbon::parse($toDate)->endOfDay(),
            ]);

        if ($request->filled('customer_id')) {
            $query->where('customer_id', (int) $request->customer_id);
        }

        return $query->orderByDesc('id');
    }

    private function summarizeSaleListRow(Sale $sale): array
    {
        $received = (float) ($sale->received_amount ?? 0);
        $refundPaid = (float) ($sale->paid_amount ?? 0);
        $pending = max(0, (float) ($sale->net_total ?? 0) - ($received - $refundPaid));

        return [
            'id' => (int) $sale->id,
            'voucher_no' => (string) ($sale->voucher_no ?? '-'),
            'sale_date' => $sale->sale_date ? Carbon::parse($sale->sale_date)->format('d-m-Y') : '-',
            'customer_name' => optional($sale->customer)->name ?? '-',
            'qty' => (int) ($sale->sum_qty ?? 0),
            'gross_wt' => (float) ($sale->sum_gross_weight ?? 0),
            'net_wt' => (float) ($sale->sum_net_weight ?? 0),
            'fine_wt' => (float) ($sale->sum_fine_weight ?? 0),
            'metal_amt' => (float) ($sale->sum_metal_amount ?? 0),
            'labour_amt' => (float) ($sale->sum_labour_amount ?? 0),
            'other_amt' => (float) ($sale->sum_other_amount ?? 0),
            'total_amt' => (float) ($sale->net_total ?? 0),
            'received_amt' => $received,
            'refund_amt' => $refundPaid,
            'pending_amt' => $pending,
            'created_by' => optional($sale->creator)->name ?? '-',
        ];
    }



    /**
     * Show create form
     */
    public function create($slug)
    {
        // Get company using slug
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->get();

        $itemsets = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_sold', 0)
            ->get();

        return view('company.sales.create', [
            'company' => $company,
            'customers' => $customers,
            'itemsets' => $itemsets,
            'isEdit' => false,
            'sale' => null,
            'editableItems' => collect(),
        ]);
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $saleId = (int) Crypt::decryptString($encryptedId);

        $sale = Sale::with('saleItems.itemset.item')
            ->where('company_id', $company->id)
            ->findOrFail((int) $saleId);

        $customers = Customer::where('company_id', $company->id)
            ->where('is_active', 1)
            ->get();

        $itemsets = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_sold', 0)
            ->get();

        $editableItems = $sale->saleItems->map(function ($row) {
            return [
                'itemset_id' => (int) ($row->itemset_id ?? 0),
                'item_id' => (int) ($row->product_id ?? optional($row->itemset)->item_id ?? 0),
                'approval_id' => (int) ($row->approval_item_id ?? 0),
                'name' => optional(optional($row->itemset)->item)->item_name ?? '',
                'metal_type' => $this->normalizeMetalType(optional(optional($row->itemset)->item)->metal ?? null),
                'code' => optional($row->itemset)->qr_code ?? '',
                'huid' => optional($row->itemset)->HUID ?? '',
                'gross_weight' => (float) ($row->gross_weight ?? 0),
                'other_weight' => (float) ($row->other_weight ?? 0),
                'net_weight' => (float) ($row->net_weight ?? 0),
                'purity' => (float) ($row->purity ?? 0),
                'waste_percent' => (float) ($row->waste_percent ?? 0),
                'net_purity' => (float) ($row->net_purity ?? 0),
                'fine_weight' => (float) ($row->fine_weight ?? 0),
                'metal_rate' => (float) ($row->metal_rate ?? 0),
                'metal_amount' => (float) ($row->metal_amount ?? 0),
                'labour_rate' => (float) ($row->labour_rate ?? 0),
                'labour_amount' => (float) ($row->labour_amount ?? 0),
                'other_amount' => (float) ($row->other_amount ?? 0),
                'total_amount' => (float) ($row->total_amount ?? 0),
                'remarks' => (string) ($row->remarks ?? ''),
                'other_charges' => [],
            ];
        });

        return view('company.sales.create', [
            'company' => $company,
            'customers' => $customers,
            'itemsets' => $itemsets,
            'isEdit' => true,
            'sale' => $sale,
            'editableItems' => $editableItems,
        ]);
    }


    public function search(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $search = trim((string) $request->search);
        $limit = max(10, min((int) $request->input('limit', 1000), 2000));

        $items = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->where(function ($q) use ($search) {
                $q->where('qr_code', 'like', '%' . $search . '%')
                    ->orWhere('HUID', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%')
                    ->orWhereHas('item', function ($q2) use ($search) {
                        $q2->where('item_name', 'like', '%' . $search . '%');
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $itemSetItemIds = $items->pluck('item_id')->filter()->unique()->values()->all();
        $itemOnly = Item::query()
            ->where('company_id', $company->id)
            ->whereNotIn('id', $itemSetItemIds)
            ->where(function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_code', 'like', '%' . $search . '%');
            })
            ->orderBy('item_name')
            ->limit($limit)
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);

        if (Schema::hasColumn('items', 'is_active')) {
            $itemOnly = Item::query()
                ->where('company_id', $company->id)
                ->where('is_active', 1)
                ->whereNotIn('id', $itemSetItemIds)
                ->where(function ($q) use ($search) {
                    $q->where('item_name', 'like', '%' . $search . '%')
                        ->orWhere('item_code', 'like', '%' . $search . '%');
                })
                ->orderBy('item_name')
                ->limit($limit)
                ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);
        }

        $itemSetRows = $items->map(function ($item) {
            $gross = (float) ($item->gross_weight ?? 0);
            $otherWeight = (float) ($item->other ?? 0);
            $net = (float) ($item->net_weight ?? ($gross - $otherWeight));
            $purity = (float) (optional($item->item)->outward_purity ?? 0);
            $wastePercent = 0;
            $netPurity = $purity + $wastePercent;
            $fineWeight = $net * $netPurity / 100;
            $metalRate = 0;
            $metalAmount = $fineWeight * $metalRate;
            $labourRate = (float) ($item->sale_labour_rate ?? optional($item->item)->labour_rate ?? 0);
            $labourAmount = $net * $labourRate;
            $otherAmount = (float) ($item->sale_other ?? 0);
            $totalAmount = $metalAmount + $labourAmount + $otherAmount;

            return [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'name' => $item->item->item_name ?? '',
                'metal_type' => $this->normalizeMetalType(optional($item->item)->metal ?? null),
                'code' => $item->qr_code,
                'huid' => $item->HUID,
                'gross_weight' => $gross,
                'other_weight' => $otherWeight,
                'net_weight' => $net,
                'purity' => $purity,
                'waste_percent' => $wastePercent,
                'net_purity' => $netPurity,
                'fine_weight' => $fineWeight,
                'metal_rate' => $metalRate,
                'metal_amount' => $metalAmount,
                'labour_rate' => $labourRate,
                'labour_amount' => $labourAmount,
                'other_amount' => $otherAmount,
                'total_amount' => $totalAmount,
                'remarks' => (string) ($item->remarks ?? ''),
                'is_item_only' => false,
            ];
        })->values();

        $itemOnlyRows = $itemOnly->map(function ($item) {
            return [
                'id' => 0,
                'item_id' => (int) $item->id,
                'name' => (string) ($item->item_name ?? ''),
                'metal_type' => $this->normalizeMetalType($item->metal ?? null),
                'code' => (string) ($item->item_code ?? ''),
                'huid' => '',
                'gross_weight' => 0,
                'other_weight' => 0,
                'net_weight' => 0,
                'purity' => (float) ($item->outward_purity ?? 0),
                'waste_percent' => 0,
                'net_purity' => 0,
                'fine_weight' => 0,
                'metal_rate' => 0,
                'metal_amount' => 0,
                'labour_rate' => (float) ($item->labour_rate ?? 0),
                'labour_amount' => 0,
                'other_amount' => 0,
                'total_amount' => 0,
                'remarks' => '',
                'is_item_only' => true,
            ];
        })->values();

        return response()->json($itemSetRows->concat($itemOnlyRows)->values());
    }
    /**
     * Get itemset by QR OR manual select
     */
    public function getItemset(Request $request, $company)
    {

        $query = ItemSet::where('company_id', $company->id)
            ->where('is_sold', 0);

        if ($request->qr_code) {
            $query->where('qr_code', $request->qr_code);
        }

        if ($request->itemset_id) {
            $query->where('id', $request->itemset_id);
        }

        $item = $query->first();

        if (!$item) {

            return response()->json([
                'success' => false,
                'message' => 'Item not found or already sold'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }



    /**
     * Store new sale
     */
    public function store(Request $request, $slug)
    {
        DB::beginTransaction();

        try {

            $company = Company::where('slug', $slug)->firstOrFail();
            $request->validate([
                'customer_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'items.*' => 'required|integer',
                'received_amount' => 'nullable|numeric|min:0',
                'additional_received_amount' => 'nullable|numeric|min:0',
            ]);

            $customerExists = Customer::where('company_id', $company->id)
                ->where('id', (int) $request->customer_id)
                ->exists();

            if (!$customerExists) {
                throw new \Exception('Invalid customer for this company.');
            }

            $sale = Sale::create([
                'company_id'  => $company->id,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0,
                'received_amount' => (float) $request->input('received_amount', 0),
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
                'remarks'     => $request->input('voucher_remarks'),
                'employee_id' => optional($request->user())->id,
                'modified_count' => 0,
            ]);

            $initialReceived = (float) $request->input('received_amount', 0);
            if ($initialReceived > 0) {
                SalePayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'amount' => $initialReceived,
                    'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                    'payment_mode' => $request->input('payment_mode'),
                    'payment_reference' => $request->input('payment_reference'),
                    'payment_note' => $request->input('payment_note'),
                    'created_by' => optional($request->user())->id,
                ]);
            }

            $total = 0;
            $approvalIds = [];

            foreach ($request->items as $index => $itemsetIdRaw) {

                $itemsetId = (int) ($itemsetIdRaw ?? 0);
                $productId = (int) ($request->item_ids[$index] ?? 0);

                if ($itemsetId <= 0 && $productId <= 0) {
                    continue;
                }

                $approvalItemId = $request->approval_item_ids[$index] ?? null;
                $item = null;
                if ($itemsetId > 0) {
                    $itemQuery = ItemSet::where('company_id', $company->id)
                        ->where('id', $itemsetId);

                    if (empty($approvalItemId)) {
                        $itemQuery->where('is_sold', 0);
                    }

                    $item = $itemQuery->first();

                    if (!$item) {
                        throw new \Exception("Item not found/already sold: {$itemsetId}");
                    }
                } else {
                    $directItem = Item::where('company_id', $company->id)->find($productId);
                    if (!$directItem) {
                        throw new \Exception("Direct item not found: {$productId}");
                    }
                }

                // ❗ prevent double sale
                // if ($item->is_sold == 1) {
                //     throw new \Exception("Item already sold ID: " . $itemsetId);
                // }


                // ✅ SAVE SALE ITEM
                SaleItem::create([
                    'sale_id'          => $sale->id,
                    'itemset_id'       => $item ? $item->id : null,
                    'product_id'       => $productId ?: (int) ($item->item_id ?? 0),
                    'approval_item_id' => $approvalItemId,
                    'gross_weight'     => $request->gross_weight[$index] ?? ($item->gross_weight ?? 0),
                    'other_weight'     => $request->other_weight[$index] ?? ($item->other ?? 0),
                    'net_weight'       => $request->net_weight[$index] ?? 0,
                    'purity'           => $request->purity[$index] ?? 0,
                    'waste_percent'    => $request->waste_percent[$index] ?? 0,
                    'net_purity'       => $request->net_purity[$index] ?? 0,
                    'fine_weight'      => $request->fine_weight[$index] ?? 0,
                    'metal_rate'       => $request->metal_rate[$index] ?? 0,
                    'metal_amount'     => $request->metal_amount[$index] ?? 0,
                    'labour_rate'      => $request->labour_rate[$index] ?? 0,
                    'labour_amount'    => $request->labour_amount[$index] ?? 0,
                    'other_amount'     => $request->other_amount[$index] ?? 0,
                    'total_amount'     => $request->total_amount[$index] ?? 0,
                    'remarks'          => $request->remarks[$index] ?? null,
                ]);

                // ✅ mark itemset sold (only for itemset flow)
                if ($item) {
                    $item->update(['is_sold' => 1]);
                }

                // ✅ UPDATE APPROVAL ITEM
                if (!empty($approvalItemId)) {

                    ApprovalItem::whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })->where('id', (int) $approvalItemId)
                        ->update(['status' => 'sold']);

                    $approval = ApprovalItem::whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })->find((int) $approvalItemId);

                    if ($approval) {
                        $approvalIds[] = $approval->approval_id;
                    }
                }

                $total += $request->total_amount[$index] ?? 0;
            }

            {
                $totalFineWeight = collect($request->input('fine_weight', []))
                    ->reduce(fn($sum, $v) => $sum + (float) $v, 0.0);
                $advanceSummaryNow = $this->getAdvanceSummary($company->id, (int) $sale->customer_id);
                $availableSilver = (float) ($advanceSummaryNow['silver'] ?? 0);
            }

            // ✅ update total
            $sale->update(['net_total' => $total]);
            $this->syncSilverAdvanceUsageForSale(
                $company->id,
                $sale,
                true,
                optional($request->user())->id
            );

            // ✅ UPDATE APPROVAL HEADER STATUS
            foreach (array_unique($approvalIds) as $approvalId) {

                $totalItems = ApprovalItem::where('approval_id', $approvalId)->count();

                $soldItems = ApprovalItem::where('approval_id', $approvalId)
                    ->where('status', 'sold')
                    ->count();

                if ($soldItems == 0) {
                    $status = 'open';
                } elseif ($soldItems < $totalItems) {
                    $status = 'partial';
                } else {
                    $status = 'closed';
                }

                ApprovalHeader::where('company_id', $company->id)
                    ->where('id', $approvalId)
                    ->update(['status' => $status]);
            }

            DB::commit();

            return redirect()
                ->route('company.sales.index', $company->slug)
                ->with('success', 'Sale created successfully');
        } catch (\Exception $e) {

            DB::rollback();

            return back()->with('error', $e->getMessage());
        }
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        DB::beginTransaction();

        try {
            $company = Company::where('slug', $slug)->firstOrFail();
            $saleId = (int) Crypt::decryptString($encryptedId);

            $sale = Sale::with('saleItems')
                ->where('company_id', $company->id)
                ->findOrFail((int) $saleId);

            $request->validate([
                'customer_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'items.*' => 'required|integer',
                'received_amount' => 'nullable|numeric|min:0',
                'additional_received_amount' => 'nullable|numeric|min:0',
            ]);

            $customerExists = Customer::where('company_id', $company->id)
                ->where('id', (int) $request->customer_id)
                ->exists();
            if (!$customerExists) {
                throw new \Exception('Invalid customer for this company.');
            }

            $incomingReceived = (float) $request->input('received_amount', $sale->received_amount ?? 0);
            $additionalReceived = (float) $request->input('additional_received_amount', 0);
            $finalReceived = $additionalReceived > 0
                ? ((float) ($sale->received_amount ?? 0) + $additionalReceived)
                : $incomingReceived;

            $baseEffectiveReceived = (float) ($sale->received_amount ?? 0) - (float) ($sale->paid_amount ?? 0);

            $sale->update([
                'customer_id' => (int) $request->customer_id,
                'received_amount' => $finalReceived,
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
                'remarks' => $request->input('voucher_remarks'),
            ]);

            if ($additionalReceived > 0) {
                if ((float) ($sale->payments()->sum('amount')) <= 0 && $baseEffectiveReceived > 0) {
                    // Seed first payment for old vouchers created before payment-history tracking.
                    SalePayment::create([
                        'company_id' => $company->id,
                        'sale_id' => $sale->id,
                        'amount' => $baseEffectiveReceived,
                        'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                        'payment_mode' => $sale->payment_mode,
                        'payment_reference' => $sale->payment_reference,
                        'payment_note' => $sale->payment_note,
                        'created_by' => optional($request->user())->id,
                    ]);
                }

                SalePayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'amount' => $additionalReceived,
                    'paid_on' => now()->toDateString(),
                    'payment_mode' => $request->input('payment_mode'),
                    'payment_reference' => $request->input('payment_reference'),
                    'payment_note' => $request->input('payment_note'),
                    'created_by' => optional($request->user())->id,
                ]);
            } elseif ((float) ($sale->payments()->sum('amount')) <= 0 && $finalReceived > 0) {
                // Backward compatibility for old sales that only had received_amount.
                SalePayment::create([
                    'company_id' => $company->id,
                    'sale_id' => $sale->id,
                    'amount' => $finalReceived,
                    'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                    'payment_mode' => $request->input('payment_mode') ?? $sale->payment_mode,
                    'payment_reference' => $request->input('payment_reference') ?? $sale->payment_reference,
                    'payment_note' => $request->input('payment_note') ?? $sale->payment_note,
                    'created_by' => optional($request->user())->id,
                ]);
            }

            $existingItems = $sale->saleItems->keyBy('itemset_id');
            $existingItemsetIds = $existingItems->keys()->map(fn($id) => (int) $id)->values();
            $incomingItemsetIds = collect($request->items)
                ->map(fn($id) => (int) $id)
                ->filter(fn($id) => $id > 0)
                ->values();

            $approvalIds = [];

            // Remove deleted rows from sale.
            $removedItemsetIds = $existingItemsetIds->diff($incomingItemsetIds)->values();
            foreach ($removedItemsetIds as $itemsetId) {
                $saleItem = $existingItems->get($itemsetId);
                if (!$saleItem) {
                    continue;
                }

                if (!empty($saleItem->approval_item_id)) {
                    $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })->find((int) $saleItem->approval_item_id);

                    if ($approvalItem) {
                        $approvalItem->update(['status' => 'pending']);
                        $approvalIds[] = (int) $approvalItem->approval_id;
                    }
                }

                ItemSet::where('company_id', $company->id)
                    ->where('id', (int) $itemsetId)
                    ->update(['is_sold' => 0]);

                $saleItem->delete();
            }

            $total = 0;

            foreach ($request->items as $index => $itemsetIdRaw) {
                $itemsetId = (int) $itemsetIdRaw;
                if ($itemsetId <= 0) {
                    continue;
                }

                $existingSaleItem = $existingItems->get($itemsetId);
                $approvalItemId = $request->approval_item_ids[$index] ?? null;

                $itemQuery = ItemSet::where('company_id', $company->id)
                    ->where('id', $itemsetId);

                if (!$existingSaleItem && empty($approvalItemId)) {
                    $itemQuery->where('is_sold', 0);
                }

                $item = $itemQuery->first();
                if (!$item) {
                    throw new \Exception("Item not available for sale: {$itemsetId}");
                }

                $payload = [
                    'gross_weight'     => $request->gross_weight[$index] ?? $item->gross_weight ?? 0,
                    'other_weight'     => $request->other_weight[$index] ?? $item->other ?? 0,
                    'net_weight'       => $request->net_weight[$index] ?? 0,
                    'purity'           => $request->purity[$index] ?? 0,
                    'waste_percent'    => $request->waste_percent[$index] ?? 0,
                    'net_purity'       => $request->net_purity[$index] ?? 0,
                    'fine_weight'      => $request->fine_weight[$index] ?? 0,
                    'metal_rate'       => $request->metal_rate[$index] ?? 0,
                    'metal_amount'     => $request->metal_amount[$index] ?? 0,
                    'labour_rate'      => $request->labour_rate[$index] ?? 0,
                    'labour_amount'    => $request->labour_amount[$index] ?? 0,
                    'other_amount'     => $request->other_amount[$index] ?? 0,
                    'total_amount'     => $request->total_amount[$index] ?? 0,
                    'remarks'          => $request->remarks[$index] ?? null,
                    'approval_item_id' => $approvalItemId,
                ];

                if ($existingSaleItem) {
                    $oldApprovalItemId = (int) ($existingSaleItem->approval_item_id ?? 0);
                    $newApprovalItemId = (int) ($approvalItemId ?? 0);

                    $existingSaleItem->update($payload);

                    if ($oldApprovalItemId > 0 && $oldApprovalItemId !== $newApprovalItemId) {
                        $oldApproval = ApprovalItem::whereHas('approval', function ($q) use ($company) {
                            $q->where('company_id', $company->id);
                        })->find($oldApprovalItemId);

                        if ($oldApproval) {
                            $oldApproval->update(['status' => 'pending']);
                            $approvalIds[] = (int) $oldApproval->approval_id;
                        }
                    }
                } else {
                    SaleItem::create(array_merge($payload, [
                        'sale_id' => $sale->id,
                        'itemset_id' => $item->id,
                    ]));

                    $item->update(['is_sold' => 1]);
                }

                if (!empty($approvalItemId)) {
                    $approval = ApprovalItem::whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })->find((int) $approvalItemId);

                    if ($approval) {
                        $approval->update(['status' => 'sold']);
                        $approvalIds[] = (int) $approval->approval_id;
                    }
                }

                $total += (float) ($payload['total_amount'] ?? 0);
            }

            {
                $totalFineWeight = collect($request->input('fine_weight', []))
                    ->reduce(fn($sum, $v) => $sum + (float) $v, 0.0);
                $advanceSummaryNow = $this->getAdvanceSummary($company->id, (int) $request->customer_id);
                $availableSilver = (float) ($advanceSummaryNow['silver'] ?? 0);
                $existingUsed = (float) CustomerAdvanceLedger::query()
                    ->where('company_id', $company->id)
                    ->where('reference_type', 'sale')
                    ->where('reference_id', (int) $sale->id)
                    ->where('metal_type', 'silver')
                    ->sum('metal_out');
                $availableForThisSale = $availableSilver + $existingUsed;
            }

            $maxAdditionalAllowed = max(0, $total - $baseEffectiveReceived);
            if (($additionalReceived - $maxAdditionalAllowed) > 0.000001) {
                throw new \Exception('Add Payment cannot be more than pending amount (' . number_format($maxAdditionalAllowed, 2) . ').');
            }

            $sale->update(['net_total' => $total]);
            $this->syncSilverAdvanceUsageForSale(
                $company->id,
                $sale,
                true,
                optional($request->user())->id
            );
            $sale->increment('modified_count');

            foreach (array_unique($approvalIds) as $approvalId) {
                $totalItems = ApprovalItem::where('approval_id', $approvalId)->count();
                $soldItems = ApprovalItem::where('approval_id', $approvalId)
                    ->where('status', 'sold')
                    ->count();

                if ($soldItems == 0) {
                    $status = 'open';
                } elseif ($soldItems < $totalItems) {
                    $status = 'partial';
                } else {
                    $status = 'closed';
                }

                ApprovalHeader::where('company_id', $company->id)
                    ->where('id', $approvalId)
                    ->update(['status' => $status]);
            }

            DB::commit();

            return redirect()
                ->route('company.sales.index', $company->slug)
                ->with('success', 'Sale updated successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Show single sale
     */
    public function show($slug, $encryptedId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $saleId = (int) Crypt::decryptString($encryptedId);
        $sale = Sale::with('customer', 'saleItems.itemset.item')
            ->where('company_id', $company->id)
            ->findOrFail($saleId);

        $advanceSummary = $this->getAdvanceSummary($company->id, (int) ($sale->customer_id ?? 0), $sale->sale_date);
        $saleAdvanceUsage = $this->getSaleAdvanceUsage($company->id, (int) $sale->id);

        return view('company.sales.show', compact(
            'company',
            'sale',
            'advanceSummary',
            'saleAdvanceUsage'
        ));
    }

    public function viewPdf($slug, $encryptedId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $saleId = (int) Crypt::decryptString($encryptedId);

        $sale = Sale::with([
            'customer',
            'saleItems.itemset.item',   // IMPORTANT
            'payments'
        ])
            ->where('company_id', $company->id)
            ->findOrFail($saleId);

        $advanceSummary = $this->getAdvanceSummary($company->id, (int) ($sale->customer_id ?? 0), $sale->sale_date);
        $saleAdvanceUsage = $this->getSaleAdvanceUsage($company->id, (int) $sale->id);
        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale', 'company', 'advanceSummary', 'saleAdvanceUsage'))
            ->setPaper('a4', 'portrait');

        return $pdf->stream('Invoice-' . $sale->voucher_no . '.pdf');
    }

    public function approvalList($slug)
    {

        $company = Company::whereSlug($slug)->firstOrFail();

        $approvals = ApprovalHeader::with('customer')
            ->where('company_id', $company->id)
            ->where('status', '!=', 'closed')
            ->get();

        return view('company.sales.approval_list', compact('company', 'approvals'));
    }
    // public function approvalItems($slug, $id)
    // {

    //     $company = Company::whereSlug($slug)->firstOrFail();

    //     $approval = ApprovalHeader::with([
    //         'items.itemSet:id,HUID,qr_code'
    //     ])->findOrFail($id);

    //     return view('company.sales.approval_items', compact('company', 'approval'));
    // }
    public function storeFromApproval(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        DB::beginTransaction();

        try {

            // CREATE SALE
            $sale = Sale::create([
                'company_id' => $company->id,
                'customer_id' => $request->customer_id,
                'sale_date' => now(),
                'voucher_no' => 'SL' . time(),
                'employee_id' => optional($request->user())->id,
                'modified_count' => 0,
            ]);

            foreach ($request->items as $id) {

                $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($company, $request) {
                    $q->where('company_id', $company->id)
                        ->where('id', (int) $request->approval_id);
                })->findOrFail((int) $id);

                // ✅ Find correct item set
                $itemSet = $approvalItem->itemSet ?: ItemSet::where('company_id', $company->id)
                    ->find($approvalItem->itemset_id);

                if (!$itemSet) {
                    throw new \Exception("ItemSet not found for approval item {$approvalItem->id}");
                }

                // ✅ BASIC VALUES
                $gross = $approvalItem->gross_weight;
                $net   = $approvalItem->net_weight;

                // ✅ PURITY (from item_sets OR item table)
                $purity = $itemSet->purity ?? 92; // default if not exist

                // ✅ FINE WEIGHT FORMULA
                $fineWeight = ($net * $purity) / 100;

                // ✅ METAL RATE (you can take from config or DB)
                $metalRate = $itemSet->sale_labour_rate ?? 0;

                // ✅ AMOUNT CALCULATION
                $amount = $fineWeight * $metalRate;

                // ✅ SAVE SALE ITEM
                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'itemset_id'   => $itemSet->id,
                    'product_id'   => null,

                    'gross_weight' => $gross,
                    'net_weight'   => $net,
                    'purity'       => $purity,
                    'fine_weight'  => $fineWeight,

                    'metal_rate'   => $metalRate,
                    'total_amount' => $amount,
                    'remarks'      => $approvalItem->remarks,
                ]);

                // ✅ UPDATE STATUS
                $approvalItem->update([
                    'status' => 'sold'
                ]);
            }

            // UPDATE APPROVAL STATUS
            $total = ApprovalItem::where('approval_id', $request->approval_id)->count();
            $sold = ApprovalItem::where('approval_id', $request->approval_id)
                ->where('status', 'sold')->count();

            ApprovalHeader::where('id', $request->approval_id)->update([
                'status' => ($total == $sold) ? 'closed' : 'partial'
            ]);

            DB::commit();

            return response()->json(['success' => true]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json(['error' => $e->getMessage()]);
        }
    }

    public function approvalItems(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customerId = $request->customer_id;

        $approvalIds = ApprovalHeader::where('company_id', $company->id)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['open', 'partial'])
            ->pluck('id');

        $items = ApprovalItem::with(['itemSet.item', 'legacyItemSet.item'])
            ->whereIn('approval_id', $approvalIds)

            // 🔥 IMPORTANT FILTER
            ->where('status', '!=', 'sold')

            ->get()
            ->filter(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                return $itemSet && $itemSet->is_sold == 1;
            });

        return response()->json($items->values()->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            $item = optional($itemSet)->item;
            $gross = (float) ($row->gross_weight ?? 0);
            $otherWeight = (float) ($row->other_weight ?? 0);
            $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
            $purity = (float) ($row->purity ?? optional($item)->outward_purity ?? 0);
            $wastePercent = (float) ($row->waste_percent ?? 0);
            $netPurity = (float) ($row->net_purity ?? ($purity + $wastePercent));
            $fineWeight = (float) ($row->total_fine_weight ?? ($net * $netPurity / 100));
            $metalRate = (float) ($row->metal_rate ?? 0);
            $metalAmount = (float) ($row->metal_amount ?? ($fineWeight * $metalRate));
            $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $labourAmount = (float) ($row->labour_amount ?? ($net * $labourRate));
            $otherAmount = (float) ($row->other_amount ?? optional($itemSet)->sale_other ?? 0);
            $totalAmount = (float) ($row->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));

            return [
                'approval_item_id' => $row->id,
                'approval_id' => $row->approval_id,
                'itemset_id'  => $row->itemset_id ?? $row->item_id,
                'item_id'     => $row->item_id ?? optional($itemSet)->item_id,
                'name'        => optional($item)->item_name,
                'metal_type'  => $this->normalizeMetalType(optional($item)->metal ?? null),
                'code'        => $row->qr_code ?? optional($itemSet)->qr_code ?? '',
                'huid'        => $row->huid ?? optional($itemSet)->HUID,
                'gross_weight'       => $gross,
                'other_weight'       => $otherWeight,
                'net_weight'         => $net,
                'purity'             => $purity,
                'waste_percent'      => $wastePercent,
                'net_purity'         => $netPurity,
                'fine_weight'        => $fineWeight,
                'metal_rate'         => $metalRate,
                'metal_amount'       => $metalAmount,
                'labour_rate'        => $labourRate,
                'labour_amount'      => $labourAmount,
                'other_amount'       => $otherAmount,
                'total_amount'       => $totalAmount,
                'remarks'            => (string) ($row->remarks ?? ''),
            ];
        })->values());
    }

    private function getAdvanceSummary(int $companyId, int $customerId, $asOnDate = null): array
    {
        if ($customerId <= 0) {
            return [
                'cash' => 0.0,
                'gold' => 0.0,
                'silver' => 0.0,
                'other' => 0.0,
            ];
        }

        $base = CustomerAdvanceLedger::query()
            ->where('company_id', $companyId)
            ->where('customer_id', $customerId);

        if (!empty($asOnDate)) {
            $base->whereDate('entry_date', '<=', \Carbon\Carbon::parse($asOnDate)->toDateString());
        }

        $cash = (clone $base)->selectRaw('COALESCE(SUM(cash_in),0) - COALESCE(SUM(cash_out),0) as bal')->value('bal');

        $metal = (clone $base)
            ->whereNotNull('metal_type')
            ->selectRaw('metal_type, COALESCE(SUM(metal_in),0) - COALESCE(SUM(metal_out),0) as bal')
            ->groupBy('metal_type')
            ->pluck('bal', 'metal_type');

        return [
            'cash' => (float) $cash,
            'gold' => (float) ($metal['gold'] ?? 0),
            'silver' => (float) ($metal['silver'] ?? 0),
            'other' => (float) ($metal['other'] ?? 0),
        ];
    }

    private function syncSilverAdvanceUsageForSale(int $companyId, Sale $sale, bool $useSilverBalance = false, ?int $userId = null): void
    {
        CustomerAdvanceLedger::query()
            ->where('company_id', $companyId)
            ->where('reference_type', 'sale')
            ->where('reference_id', (int) $sale->id)
            ->where('entry_type', 'purchase_adjust_metal')
            ->delete();

        if (!$useSilverBalance) {
            return;
        }

        if ((int) ($sale->customer_id ?? 0) <= 0) {
            return;
        }

        $asOnDate = Carbon::parse($sale->sale_date ?? now())->toDateString();
        $items = $sale->saleItems()->with('itemset.item')->get();
        $metalFineUsage = ['gold' => 0.0, 'silver' => 0.0, 'other' => 0.0];

        foreach ($items as $row) {
            $fine = (float) ($row->fine_weight ?? 0);
            if ($fine <= 0) {
                continue;
            }
            $metalType = $this->normalizeMetalType(optional(optional($row->itemset)->item)->metal ?? null);
            $metalFineUsage[$metalType] = (float) ($metalFineUsage[$metalType] ?? 0) + $fine;
        }

        foreach ($metalFineUsage as $metalType => $metalOut) {
            if ($metalOut <= 0) {
                continue;
            }
            CustomerAdvanceLedger::create([
                'company_id' => $companyId,
                'customer_id' => (int) $sale->customer_id,
                'entry_date' => $asOnDate,
                'entry_type' => 'purchase_adjust_metal',
                'payment_mode' => null,
                'cash_in' => 0,
                'cash_out' => 0,
                'metal_type' => $metalType,
                'metal_in' => 0,
                'metal_out' => round((float) $metalOut, 3),
                'rate' => 0,
                'reference_type' => 'sale',
                'reference_id' => (int) $sale->id,
                'remarks' => 'Auto ' . $metalType . ' adjusted from sale fine weight',
                'created_by' => $userId,
            ]);
        }
    }

    private function normalizeMetalType(?string $metal): string
    {
        $m = strtolower(trim((string) $metal));
        if ($m === 'gold' || str_contains($m, 'gold')) {
            return 'gold';
        }
        if ($m === 'silver' || str_contains($m, 'silver')) {
            return 'silver';
        }
        return 'other';
    }

    public function customerAdvance(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $customerId = (int) $request->query('customer_id', 0);
        if ($customerId <= 0) {
            return response()->json([
                'success' => true,
                'cash' => 0,
                'silver' => 0,
                'gold' => 0,
                'other' => 0,
            ]);
        }

        $summary = $this->getAdvanceSummary($company->id, $customerId);
        return response()->json([
            'success' => true,
            'cash' => (float) ($summary['cash'] ?? 0),
            'silver' => (float) ($summary['silver'] ?? 0),
            'gold' => (float) ($summary['gold'] ?? 0),
            'other' => (float) ($summary['other'] ?? 0),
        ]);
    }

    private function getSaleAdvanceUsage(int $companyId, int $saleId): array
    {
        $usage = CustomerAdvanceLedger::query()
            ->where('company_id', $companyId)
            ->where('reference_type', 'sale')
            ->where('reference_id', $saleId)
            ->selectRaw('COALESCE(SUM(cash_out),0) as cash_used')
            ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = "gold" THEN metal_out ELSE 0 END),0) as gold_used')
            ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = "silver" THEN metal_out ELSE 0 END),0) as silver_used')
            ->selectRaw('COALESCE(SUM(CASE WHEN metal_type = "other" THEN metal_out ELSE 0 END),0) as other_used')
            ->first();

        $fineRequired = (float) SaleItem::query()
            ->where('sale_id', $saleId)
            ->sum('fine_weight');
        $silverUsed = (float) ($usage->silver_used ?? 0);
        $saleCustomerId = (int) Sale::query()->where('id', $saleId)->value('customer_id');
        $closingSummary = $this->getAdvanceSummary($companyId, $saleCustomerId);
        $closingSilver = (float) ($closingSummary['silver'] ?? 0);
        $openingSilver = $closingSilver + $silverUsed;
        $silverDebit = max(0, $fineRequired - $openingSilver);
        $silverCredit = max(0, $openingSilver - $fineRequired);

        return [
            'cash_used' => (float) ($usage->cash_used ?? 0),
            'gold_used' => (float) ($usage->gold_used ?? 0),
            'silver_used' => $silverUsed,
            'other_used' => (float) ($usage->other_used ?? 0),
            'fine_required' => $fineRequired,
            'silver_debit' => $silverDebit,
            'silver_credit' => $silverCredit,
            'silver_opening' => $openingSilver,
            'silver_closing' => $closingSilver,
        ];
    }
}

