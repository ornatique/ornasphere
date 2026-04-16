<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ItemSet;
use App\Models\Customer;
use App\Models\SaleCart;
use App\Models\ApprovalItem;
use App\Models\ApprovalHeader;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;

class SaleApiController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;
        $companyId = $company->id;

        $sales = Sale::with('customer')
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        $data = $sales->map(function ($sale) use ($company) {
            return [
                'id' => $sale->id,
                'company_id' => $sale->company_id,
                'customer_id' => $sale->customer_id,
                'voucher_no' => $sale->voucher_no,
                'sale_date' => $sale->sale_date,
                'net_total' => $sale->net_total,
                'customer' => $sale->customer,
                // Signed URL works in browser/app without auth header.
                'pdf_url' => URL::temporarySignedRoute(
                    'api.sales.pdf.public',
                    now()->addMinutes(60),
                    [
                        'id' => $sale->id,
                        'company_id' => $company->id,
                    ]
                ),
                // Keep bearer-token URL for direct API calls.
                'api_pdf_url' => route('api.sales.pdf', [
                    'id' => $sale->id,
                ]),
                // Keep web URL for browser session based access.
                'web_pdf_url' => route('company.sales.pdf', [
                    'slug' => $company->slug,
                    'sale' => $sale->id
                ])
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function customerlist(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    public function addToCart(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'qr_code' => 'required|string',
        ]);

        $item = ItemSet::where('company_id', $user->company_id)
            ->where('qr_code', $request->qr_code)
            ->where('is_sold', 0)
            ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found or already sold'
            ]);
        }

        $exists = SaleCart::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->where('itemset_id', $item->id)
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already added. Please add different product.'
            ]);
        }

        SaleCart::create([
            'user_id'    => $user->id,
            'company_id' => $user->company_id,
            'itemset_id' => $item->id,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Product added to cart',
            'item' => $item
        ]);
    }

    public function cartItems()
    {
        $user = auth()->user();

        $items = SaleCart::with('itemset.item')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
        ]);
    }

    public function removeCartItem($id)
    {
        $user = auth()->user();

        $cartItem = SaleCart::where('id', $id)
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found in cart'
            ]);
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item removed from cart'
        ]);
    }

    public function qrListApi(Request $request)
    {
        $user = auth()->user();

        $itemSets = ItemSet::with('item:id,item_name')
            ->where('company_id', $user->company_id)
            ->where('is_final', 1)
            ->whereNotNull('qr_code')
            ->latest()
            ->get()
            ->map(function ($set) {
                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $set->qr_code,
                    size: 120,
                    margin: 5
                );

                $result = $builder->build();
                $base64 = base64_encode($result->getString());

                return [
                    'id' => $set->id,
                    'item_name' => $set->item ? $set->item->item_name : 'N/A',
                    'serial_no' => $set->serial_no,
                    'qr_code' => $set->qr_code,
                    'gross_weight' => (float) ($set->gross_weight ?? 0),
                    'other_weight' => (float) ($set->other ?? 0),
                    'net_weight' => (float) ($set->net_weight ?? 0),
                    'sale_labour_formula' => $set->sale_labour_formula ?? null,
                    'labour_rate' => (float) ($set->sale_labour_rate ?? 0),
                    'labour_amount' => (float) ($set->sale_labour_amount ?? 0),
                    'sale_other' => (float) ($set->sale_other ?? 0),
                    'is_printed' => (int) ($set->is_printed ?? 0),
                    'printed_at' => $set->printed_at ? \Carbon\Carbon::parse($set->printed_at)->format('d-m-Y h:i A') : null,
                    'qr_image' => 'data:image/png;base64,' . $base64,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $itemSets
        ]);
    }

    public function downloadQrPdf(Request $request)
    {
        $user = auth()->user();

        $idsParam = $request->input('ids', []);
        if (is_string($idsParam)) {
            $ids = array_values(array_filter(explode(',', $idsParam)));
        } elseif (is_array($idsParam)) {
            $ids = $idsParam;
        } else {
            $ids = [];
        }

        if (empty($ids)) {
            return response()->json([
                'success' => false,
                'message' => 'Please provide at least one id in ids.',
            ], 422);
        }

        $itemSets = ItemSet::with('item:id,item_name')
            ->where('company_id', $user->company_id)
            ->whereIn('id', $ids)
            ->where('is_final', 1)
            ->get();

        if ($itemSets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No labels found for provided ids.',
            ], 404);
        }

        ItemSet::where('company_id', $user->company_id)
            ->whereIn('id', $itemSets->pluck('id'))
            ->update([
                'is_printed' => 1,
                'printed_at' => now(),
            ]);

        foreach ($itemSets as $set) {
            $builder = new Builder(
                writer: new PngWriter(),
                data: $set->qr_code,
                size: 200,
                margin: 10
            );

            $result = $builder->build();
            $set->qr_base64 = 'data:image/png;base64,' . base64_encode($result->getString());
        }

        // Keep API PDF visually same as web print layout.
        $pdf = Pdf::loadView('company.item_sets.print_pdf', compact('itemSets'))
            ->setPaper([0, 0, 609.45, 340.16]);

        return $pdf->download('qr-codes.pdf');
    }

    public function confirmSale(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();

            $request->validate([
                'customer_id' => 'required|integer',
            ]);

            $customerExists = Customer::where('company_id', $user->company_id)
                ->where('id', (int) $request->customer_id)
                ->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer for this company',
                ], 422);
            }

            $cartItems = SaleCart::where('user_id', auth()->id())
                ->where('company_id', $user->company_id)
                ->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart empty']);
            }

            $sale = Sale::create([
                'company_id'  => $user->company_id,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);

            $total = 0;

            foreach ($cartItems as $cart) {
                $item = ItemSet::with('item')
                    ->where('company_id', $user->company_id)
                    ->where('is_sold', 0)
                    ->find($cart->itemset_id);

                if (!$item) {
                    throw new \Exception('Item not available for sale');
                }

                $purity = (float) (optional($item->item)->outward_purity ?? 0);
                $labourAmount = (float) ($item->sale_labour_amount ?? (($item->net_weight ?? 0) * ($item->sale_labour_rate ?? 0)));
                $otherAmount = (float) ($item->sale_other ?? 0);
                $lineTotal = $labourAmount + $otherAmount;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'itemset_id' => $item->id,
                    'gross_weight' => $item->gross_weight,
                    'other_weight' => $item->other ?? 0,
                    'net_weight' => $item->net_weight,
                    'purity' => $purity,
                    'waste_percent' => 0,
                    'net_purity' => $purity,
                    'fine_weight' => ($item->net_weight ?? 0) * ($purity / 100),
                    'metal_rate' => 0,
                    'metal_amount' => 0,
                    'labour_rate' => $item->sale_labour_rate ?? 0,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $lineTotal,
                ]);

                $item->update(['is_sold' => 1]);
                $total += $lineTotal;
            }

            $sale->update(['net_total' => $total]);

            SaleCart::where('user_id', auth()->id())
                ->where('company_id', $user->company_id)
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale,
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function getItemByQr(Request $request)
    {
        $companyId = $request->user()->company_id;

        $item = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('qr_code', $request->qr_code)
            ->where('is_sold', 0)
            ->first();

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

    // Exact scanner endpoint for app flow
    public function scanQr(Request $request)
    {
        return $this->getItemByQr($request);
    }

    // Add Label From Approval (same as web)
    public function approvalItems(Request $request)
    {
        $companyId = $request->user()->company_id;
        $customerId = (int) (
            $request->input('customer_id')
            ?? $request->input('customer')
            ?? $request->input('party_id')
            ?? 0
        );

        if ($customerId <= 0) {
            return response()->json([
                'success' => true,
                'data' => []
            ]);
        }

        $approvalIds = ApprovalHeader::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['open', 'partial'])
            ->pluck('id');

        $rows = ApprovalItem::with(['itemSet.item', 'legacyItemSet.item'])
            ->whereIn('approval_id', $approvalIds)
            ->where('status', '!=', 'sold')
            ->get()
            ->filter(function ($row) {
                // Match web logic exactly:
                // only rows linked to sold itemsets are eligible to convert into sale.
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                return $itemSet && (int) $itemSet->is_sold === 1;
            })
            ->values()
            ->map(function ($row) {
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
                    'approval_item_id' => $row->id,
                    'approval_id' => $row->approval_id,
                    'itemset_id' => $row->itemset_id ?? $row->item_id ?? optional($itemSet)->id,
                    'item_id' => $row->item_id ?? optional($itemSet)->item_id,
                    'qty' => 1,
                    'name' => optional($item)->item_name,
                    'code' => $row->qr_code ?? optional($itemSet)->qr_code ?? '',
                    'huid' => $row->huid ?? optional($itemSet)->HUID,
                    'gross_weight' => $gross,
                    'gross_wt' => $gross,
                    'other_weight' => $otherWeight,
                    'other_wt' => $otherWeight,
                    'net_weight' => $net,
                    'net_wt' => $net,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'waste_pct' => $wastePercent,
                    'net_purity' => $netPurity,
                    'fine_weight' => $fineWeight,
                    'fine_wt' => $fineWeight,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount,
                    'metal_amt' => $metalAmount,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'labour_amt' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'other_amt' => $otherAmount,
                    'total_amount' => $totalAmount,
                    'total_amt' => $totalAmount,
                    'status' => $row->status,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }

    public function getItemset(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = ItemSet::where('company_id', $companyId)
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

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = $user->company_id;
        $customerId = (int) (
            $request->input('customer_id')
            ?? $request->input('customer')
            ?? $request->input('party_id')
            ?? 0
        );

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.itemset_id' => 'required|integer',
        ]);

        if ($customerId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Customer is required.'
            ], 422);
        }

        validator([
            'customer_id' => $customerId
        ], [
            'customer_id' => 'required|integer',
        ])->validate();

        $customerExists = Customer::where('company_id', $companyId)
            ->where('id', $customerId)
            ->exists();
        if (!$customerExists) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid customer for this company.'
            ], 422);
        }

        $request->validate([
            'items' => 'required|array|min:1',
            'items.*.itemset_id' => 'required|integer',
        ]);

        DB::beginTransaction();

        try {
            $sale = Sale::create([
                'company_id'  => $companyId,
                'customer_id' => $customerId,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);

            $total = 0;
            $approvalIds = [];
            $soldItemsetIds = [];

            foreach ($request->items as $item) {
                $itemSetQuery = ItemSet::where('company_id', $companyId)
                    ->where('id', (int) $item['itemset_id']);

                // For normal sale/scanner/manual rows, only unsold labels are allowed.
                // For approval-conversion rows, label can already be marked sold (outward on approval).
                if (empty($item['approval_item_id'])) {
                    $itemSetQuery->where('is_sold', 0);
                }

                $itemSet = $itemSetQuery->first();
                if (!$itemSet) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ItemSet not found or not available for sale: ' . ($item['itemset_id'] ?? ''),
                    ], 422);
                }

                $grossWeight = (float) ($item['gross_weight'] ?? $item['gross_wt'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($item['other_weight'] ?? $item['other_wt'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($item['net_weight'] ?? $item['net_wt'] ?? $itemSet->net_weight ?? max(0, $grossWeight - $otherWeight));
                $purity = (float) ($item['purity'] ?? optional($itemSet->item)->outward_purity ?? 0);
                $wastePercent = (float) ($item['waste_percent'] ?? $item['waste_pct'] ?? 0);
                $netPurity = (float) ($item['net_purity'] ?? ($purity - $wastePercent));
                $fineWeight = (float) ($item['fine_weight'] ?? $item['fine_wt'] ?? ($netWeight * $netPurity / 100));
                $metalRate = (float) ($item['metal_rate'] ?? 0);
                $metalAmount = (float) ($item['metal_amount'] ?? $item['metal_amt'] ?? ($netWeight * $metalRate));
                $labourRate = (float) ($item['labour_rate'] ?? $itemSet->sale_labour_rate ?? 0);
                $labourAmount = (float) ($item['labour_amount'] ?? $item['labour_amt'] ?? $itemSet->sale_labour_amount ?? ($netWeight * $labourRate));
                $otherAmount = (float) ($item['other_amount'] ?? $item['other_amt'] ?? $itemSet->sale_other ?? 0);
                $lineTotal = (float) ($item['amount'] ?? $item['total_amount'] ?? $item['total_amt'] ?? ($metalAmount + $labourAmount + $otherAmount));
                $qty = (int) ($item['qty'] ?? 1);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'itemset_id' => $itemSet->id,
                    'product_id' => $item['product_id'] ?? $itemSet->item_id ?? null,
                    'approval_item_id' => $item['approval_item_id'] ?? null,
                    'qty' => $qty,
                    'gross_weight' => $grossWeight,
                    'other_weight' => $otherWeight,
                    'net_weight' => $netWeight,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'fine_weight' => $fineWeight,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount,
                    'other_amount' => $otherAmount,
                    'total_amount' => $lineTotal,
                ]);

                if (!empty($item['approval_item_id'])) {
                    $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->find((int) $item['approval_item_id']);
                    if ($approvalItem) {
                        $approvalItem->update(['status' => 'sold']);
                        $approvalIds[] = $approvalItem->approval_id;
                    }
                }

                $itemSet->update(['is_sold' => 1]);
                $soldItemsetIds[] = (int) $itemSet->id;
                $total += $lineTotal;
            }

            $sale->update(['net_total' => $total]);
            $this->refreshApprovalHeaderStatus($approvalIds);

            // Remove sold items from this user's sale cart after successful save.
            $soldItemsetIds = array_values(array_unique(array_filter($soldItemsetIds)));
            if (!empty($soldItemsetIds)) {
                SaleCart::where('company_id', $companyId)
                    ->where('user_id', $user->id)
                    ->whereIn('itemset_id', $soldItemsetIds)
                    ->delete();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $sale = Sale::with('customer', 'saleItems.itemset')
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found'
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $sale = Sale::with(['customer', 'saleItems.itemset.item'])
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found'
            ], 404);
        }

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale'))
            ->setPaper('a4', 'portrait');

        $filename = 'sale-voucher-' . ($sale->voucher_no ?: $sale->id) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    public function publicPdf(Request $request, $id)
    {
        if (!$request->hasValidSignature()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired PDF link.',
                'code' => 'INVALID_SIGNATURE',
            ], 403);
        }

        $companyId = (int) $request->query('company_id');
        if ($companyId <= 0) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid company context.',
                'code' => 'INVALID_COMPANY',
            ], 422);
        }

        $sale = Sale::with(['customer', 'saleItems.itemset.item'])
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale'))
            ->setPaper('a4', 'portrait');

        $filename = 'sale-voucher-' . ($sale->voucher_no ?: $sale->id) . '.pdf';

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }

    private function refreshApprovalHeaderStatus(array $approvalIds): void
    {
        foreach (array_unique($approvalIds) as $approvalId) {
            if (!$approvalId) {
                continue;
            }

            $totalItems = ApprovalItem::where('approval_id', $approvalId)->count();
            $doneItems = ApprovalItem::where('approval_id', $approvalId)
                ->whereIn('status', ['sold', 'returned'])
                ->count();

            if ($totalItems <= 0) {
                continue;
            }

            $status = 'open';
            if ($doneItems === $totalItems) {
                $status = 'closed';
            } elseif ($doneItems > 0 && $doneItems < $totalItems) {
                $status = 'partial';
            }

            ApprovalHeader::where('id', $approvalId)->update(['status' => $status]);
        }
    }
}
