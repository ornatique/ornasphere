<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Itemset;
use App\Models\User;
use App\Models\Company;
use App\Models\ApprovalHeader;
use App\Models\ApprovalItem;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use DB;
use Carbon\Carbon;

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

            $query = Sale::with('customer')
                ->where('company_id', $company->id);

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

                // ✅ ACTION BUTTON (VIEW PDF)
                ->addColumn('action', function ($sale) use ($company) {

                    $pdfUrl = route('company.sales.pdf', [
                        'slug' => $company->slug,
                        'sale' => $sale->id
                    ]);

                    return '
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
        return view('company.sales.index', compact('company'));
    }



    /**
     * Show create form
     */
    public function create($slug)
    {
        // Get company using slug
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = User::where('company_id', $company->id)
            ->whereRaw('LOWER(role) = ?', ['customer'])
            ->get();

        $itemsets = Itemset::with('item')
            ->where('company_id', $company->id)
            ->where('is_sold', 0)
            ->get();

        return view('company.sales.create', compact(
            'company',
            'customers',
            'itemsets'
        ));
    }


    public function search(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $items = Itemset::with('item')
            ->where('company_id', $company->id)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->where(function ($q) use ($request) {
                $q->where('qr_code', 'like', '%' . $request->search . '%')
                    ->orWhereHas('item', function ($q2) use ($request) {
                        $q2->where('item_name', 'like', '%' . $request->search . '%');
                    });
            })
            ->limit(10)
            ->get();

        return response()->json($items->map(function ($item) {
            $gross = (float) ($item->gross_weight ?? 0);
            $otherWeight = (float) ($item->other ?? 0);
            $net = (float) ($item->net_weight ?? ($gross - $otherWeight));
            $purity = (float) (optional($item->item)->outward_purity ?? 0);
            $wastePercent = 0;
            $netPurity = $purity - $wastePercent;
            $fineWeight = $net * $netPurity / 100;
            $metalRate = 0;
            $metalAmount = $net * $metalRate;
            $labourRate = (float) ($item->sale_labour_rate ?? optional($item->item)->labour_rate ?? 0);
            $labourAmount = $net * $labourRate;
            $otherAmount = (float) ($item->sale_other ?? 0);
            $totalAmount = $metalAmount + $labourAmount + $otherAmount;

            return [
                'id' => $item->id,
                'item_id' => $item->item_id,
                'name' => $item->item->item_name ?? '',
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
            ];
        }));
    }
    /**
     * Get itemset by QR OR manual select
     */
    public function getItemset(Request $request, $company)
    {

        $query = Itemset::where('company_id', $company->id)
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

            $sale = Sale::create([
                'company_id'  => $company->id,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);

            $total = 0;
            $approvalIds = [];

            foreach ($request->items as $index => $itemsetId) {

                if (empty($itemsetId)) continue;

                $item = Itemset::find($itemsetId);
                if (!$item) continue;

                // ❗ prevent double sale
                // if ($item->is_sold == 1) {
                //     throw new \Exception("Item already sold ID: " . $itemsetId);
                // }

                $approvalItemId = $request->approval_item_ids[$index] ?? null;

                // ✅ SAVE SALE ITEM
                SaleItem::create([
                    'sale_id'          => $sale->id,
                    'itemset_id'       => $item->id,
                    'approval_item_id' => $approvalItemId,
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
                ]);

                // ✅ mark item sold
                $item->update(['is_sold' => 1]);

                // ✅ UPDATE APPROVAL ITEM
                if (!empty($approvalItemId)) {

                    ApprovalItem::where('id', $approvalItemId)
                        ->update(['status' => 'sold']);

                    $approval = ApprovalItem::find($approvalItemId);

                    if ($approval) {
                        $approvalIds[] = $approval->approval_id;
                    }
                }

                $total += $request->total_amount[$index] ?? 0;
            }

            // ✅ update total
            $sale->update(['net_total' => $total]);

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

                ApprovalHeader::where('id', $approvalId)
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

    /**
     * Show single sale
     */
    public function show(Company $company, $saleId)
    {
        $sale = Sale::with('customer', 'saleItems.itemset')
            ->where('company_id', $company->id)
            ->findOrFail($saleId);

        return view('company.sales.show', compact(
            'company',
            'sale'
        ));
    }

    public function viewPdf($slug, $saleId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $sale = Sale::with([
            'customer',
            'saleItems.itemset.item'   // IMPORTANT
        ])
            ->where('company_id', $company->id)
            ->findOrFail($saleId);

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale'));

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
            ]);

            foreach ($request->items as $id) {

                $approvalItem = ApprovalItem::findOrFail($id);

                // ✅ Find correct item set
                $itemSet = ItemSet::where('item_id', $approvalItem->item_id)
                    ->where('gross_weight', $approvalItem->gross_weight)
                    ->where('net_weight', $approvalItem->net_weight)
                    ->first();

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
                    'itemset_id'   => $approvalItem->item_id,
                    'product_id'   => null,

                    'gross_weight' => $gross,
                    'net_weight'   => $net,
                    'purity'       => $purity,
                    'fine_weight'  => $fineWeight,

                    'metal_rate'   => $metalRate,
                    'total_amount' => $amount,
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

        return response()->json($items->map(function ($row) {
            $itemSet = $row->itemSet ?? $row->legacyItemSet;
            $item = optional($itemSet)->item;
            $gross = (float) ($row->gross_weight ?? 0);
            $otherWeight = (float) ($row->other_weight ?? 0);
            $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
            $purity = (float) ($row->purity ?? optional($item)->outward_purity ?? 0);
            $wastePercent = (float) ($row->waste_percent ?? 0);
            $netPurity = (float) ($row->net_purity ?? ($purity - $wastePercent));
            $fineWeight = (float) ($row->total_fine_weight ?? ($net * $netPurity / 100));
            $metalRate = (float) ($row->metal_rate ?? 0);
            $metalAmount = (float) ($row->metal_amount ?? ($net * $metalRate));
            $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $labourAmount = (float) ($row->labour_amount ?? ($net * $labourRate));
            $otherAmount = (float) ($row->other_amount ?? optional($itemSet)->sale_other ?? 0);
            $totalAmount = (float) ($row->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));

            return [
                'approval_id' => $row->id,
                'itemset_id'  => $row->itemset_id ?? $row->item_id,
                'item_id'     => $row->item_id ?? optional($itemSet)->item_id,
                'name'        => optional($item)->item_name,
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
            ];
        }));
    }
}
