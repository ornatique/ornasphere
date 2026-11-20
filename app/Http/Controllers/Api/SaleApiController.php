<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ItemSet;
use App\Models\Item;
use App\Models\Company;
use App\Models\Customer;
use App\Models\SaleCart;
use App\Models\SalePayment;
use App\Models\CustomerAdvanceLedger;
use App\Models\ApprovalItem;
use App\Models\ApprovalHeader;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Schema;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Services\TransactionItemGuard;

class SaleApiController extends Controller
{
    public function index(Request $request)
    {
        return \App\Services\SalesApiBranches::listing($request, fn ($companyId) => $this->listForCompany($request, $companyId), 'Sales Estimate');
    }

    private function listForCompany(Request $request, int $companyId)
    {
        $company = \App\Models\Company::findOrFail($companyId);

        $sales = Sale::with(['customer', 'creator', 'payments'])
            ->withSum('saleItems as total_qty', 'qty')
            ->withSum('saleItems as total_gross_weight', 'gross_weight')
            ->withSum('saleItems as total_net_weight', 'net_weight')
            ->withSum('saleItems as total_fine_weight', 'fine_weight')
            ->withSum('saleItems as total_metal_amount', 'metal_amount')
            ->withSum('saleItems as total_labour_amount', 'labour_amount')
            ->withSum('saleItems as total_other_amount', 'other_amount')
            ->where('company_id', $companyId)
            ->whereHas('customer', fn($customer) => $customer->saleParties())
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('sale_date','>=',$request->from_date))
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('sale_date','<=',$request->to_date))
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id',$request->customer_id))
            ->latest()
            ->get();

        $data = $sales->map(function ($sale) use ($company) {
            $received = (float) ($sale->received_amount ?? 0);
            $refundPaid = (float) ($sale->paid_amount ?? 0);
            $effectiveReceived = $sale->appliedReceivedAmount();
            $pending = max(0, (float) ($sale->net_total ?? 0) - $effectiveReceived);

            return [
                'id' => $sale->id,
                'company_id' => $sale->company_id,
                'customer_id' => $sale->customer_id,
                'voucher_no' => $sale->voucher_no,
                'sale_date' => $sale->sale_date,
                'remarks' => $sale->remarks,
                'can_edit_today' => true,
                'can_edit' => true,
                'qty_pcs' => (int) ($sale->total_qty ?? 0),
                'gross_weight' => (float) ($sale->total_gross_weight ?? 0),
                'net_weight' => (float) ($sale->total_net_weight ?? 0),
                'fine_weight' => (float) ($sale->total_fine_weight ?? 0),
                'metal_amount' => (float) ($sale->total_metal_amount ?? 0),
                'labour_amount' => (float) ($sale->total_labour_amount ?? 0),
                'other_amount' => (float) ($sale->total_other_amount ?? 0),
                'net_total' => $sale->net_total,
                'received_amount' => $received,
                'refund_paid_amount' => $refundPaid,
                'pending_amount' => $pending,
                'payment_mode' => $sale->payment_mode,
                'payment_reference' => $sale->payment_reference,
                'payment_note' => $sale->payment_note,
                'payment_history' => collect($sale->payments ?? [])->map(function ($p) {
                    return [
                        'id' => (int) $p->id,
                        'paid_on' => optional($p->paid_on)?->format('Y-m-d'),
                        'amount' => (float) ($p->amount ?? 0),
                        'payment_mode' => $p->payment_mode,
                        'payment_reference' => $p->payment_reference,
                        'payment_note' => $p->payment_note,
                    ];
                })->values(),
                'created_by' => optional($sale->creator)->name,
                'modified_at' => optional($sale->updated_at)?->format('Y-m-d H:i:s'),
                'modified_count' => (int) ($sale->modified_count ?? 0),
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
                    'encryptedId' => Crypt::encryptString((string) $sale->id),
                ])
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    public function exportListPdf(Request $request)
    {
        $request->merge(['export'=>'pdf']);
        return $this->index($request);
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
            ->whereHas('customer', fn($customer) => $customer->saleParties())
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
        $pending = max(0, (float) ($sale->net_total ?? 0) - $sale->appliedReceivedAmount());

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

    public function customerlist(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

        $customers = Customer::where('company_id', $companyId)
            ->where('is_active', 1)
            ->saleParties()
            ->orderBy('name')
            ->get()
            ->map(function ($customer) use ($companyId) {
                $summary = $this->getAdvanceSummary($companyId, (int) $customer->id);
                $cash = (float) ($summary['cash'] ?? 0);
                $gold = (float) ($summary['gold'] ?? 0);
                $silver = (float) ($summary['silver'] ?? 0);
                $other = (float) ($summary['other'] ?? 0);

                $row = $customer->toArray();
                $row['advance'] = [
                    'cash' => $this->formatAdvanceBalance($cash, 2, 'Advance Cash Balance'),
                    'gold' => $this->formatAdvanceBalance($gold, 3, 'Gold Fine Balance'),
                    'silver' => array_merge(
                        $this->formatAdvanceBalance($silver, 3, 'Silver Fine Balance'),
                        [
                            'used_fine_weight' => 0.0,
                            'used_label' => 'Silver Used (Fine Wt)',
                            'balance_after_use' => $silver,
                            'balance_after_use_display' => round(abs($silver), 3),
                            'balance_after_use_type' => $silver >= 0 ? 'Credit' : 'Debit',
                            'balance_after_use_label' => 'Silver Balance After Use ' . ($silver >= 0 ? 'Credit' : 'Debit'),
                        ]
                    ),
                    'other' => $this->formatAdvanceBalance($other, 3, 'Other Metal Balance'),
                ];

                // Flat keys are included for app screens that bind directly to customer rows.
                $row['advance_cash_balance'] = $cash;
                $row['advance_cash_balance_display'] = round(abs($cash), 2);
                $row['advance_cash_balance_type'] = $cash >= 0 ? 'Credit' : 'Debit';
                $row['advance_cash_balance_label'] = 'Advance Cash Balance ' . $row['advance_cash_balance_type'];
                $row['advance_gold_fine_balance'] = $gold;
                $row['advance_gold_fine_balance_display'] = round(abs($gold), 3);
                $row['advance_gold_fine_balance_type'] = $gold >= 0 ? 'Credit' : 'Debit';
                $row['advance_gold_fine_balance_label'] = 'Gold Fine Balance ' . $row['advance_gold_fine_balance_type'];
                $row['advance_silver_fine_balance'] = $silver;
                $row['advance_silver_fine_balance_display'] = round(abs($silver), 3);
                $row['advance_silver_fine_balance_type'] = $silver >= 0 ? 'Credit' : 'Debit';
                $row['advance_silver_fine_balance_label'] = 'Silver Fine Balance ' . $row['advance_silver_fine_balance_type'];
                $row['advance_silver_used_fine_weight'] = 0.0;
                $row['advance_silver_used_fine_weight_label'] = 'Silver Used (Fine Wt)';
                $row['advance_silver_balance_after_use'] = $silver;
                $row['advance_silver_balance_after_use_display'] = round(abs($silver), 3);
                $row['advance_silver_balance_after_use_type'] = $silver >= 0 ? 'Credit' : 'Debit';
                $row['advance_silver_balance_after_use_label'] = 'Silver Balance After Use ' . $row['advance_silver_balance_after_use_type'];
                $row['advance_other_fine_balance'] = $other;
                $row['advance_other_fine_balance_display'] = round(abs($other), 3);
                $row['advance_other_fine_balance_type'] = $other >= 0 ? 'Credit' : 'Debit';
                $row['advance_other_fine_balance_label'] = 'Other Metal Balance ' . $row['advance_other_fine_balance_type'];

                return $row;
            })
            ->values();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }

    // Search itemsets + direct items for sale create flow (same as web behavior)
    public function searchItemsets(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $search = trim((string) $request->input('search', ''));
        $customerId = (int) $request->input('customer_id', 0);
        $limit = max(10, min((int) $request->input('limit', 1000), 2000));
        $includeApproval = $request->boolean('include_approval', false);

        $approvalItems = collect();
        if ($includeApproval && $customerId > 0) {
            $approvalItems = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item', 'item'])
                ->whereExists(function ($q) use ($companyId, $customerId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', $companyId)
                        ->where('approval_headers.customer_id', $customerId);
                })
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(TRIM(status)) in (?, ?, ?)', ['pending', 'sold', 'returned']);
                })
                ->when(Schema::hasTable('sale_carts') && Schema::hasColumn('sale_carts', 'approval_item_id'), function ($q) use ($companyId, $request) {
                    $this->excludeApprovalSaleCartLocks($q, (int) $companyId, (int) $request->user()->id);
                })
                ->where(function ($q) use ($search) {
                    $q->where('approval_items.qr_code', 'like', '%' . $search . '%')
                        ->orWhere('approval_items.huid', 'like', '%' . $search . '%')
                        ->orWhereHas('itemSet', function ($q2) use ($search) {
                            $q2->where('qr_code', 'like', '%' . $search . '%')
                                ->orWhere('HUID', 'like', '%' . $search . '%')
                                ->orWhere('barcode', 'like', '%' . $search . '%')
                                ->orWhereHas('item', function ($q3) use ($search) {
                                    $q3->where('item_name', 'like', '%' . $search . '%')
                                        ->orWhere('item_code', 'like', '%' . $search . '%');
                                });
                        })
                        ->orWhereHas('legacyItemSet', function ($q2) use ($search) {
                            $q2->where('qr_code', 'like', '%' . $search . '%')
                                ->orWhere('HUID', 'like', '%' . $search . '%')
                                ->orWhere('barcode', 'like', '%' . $search . '%')
                                ->orWhereHas('item', function ($q3) use ($search) {
                                    $q3->where('item_name', 'like', '%' . $search . '%')
                                        ->orWhere('item_code', 'like', '%' . $search . '%');
                                });
                        })
                        ->orWhereHas('item', function ($q2) use ($search) {
                            $q2->where('item_name', 'like', '%' . $search . '%')
                                ->orWhere('item_code', 'like', '%' . $search . '%');
                        });
                })
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->filter(fn ($row) => \App\Services\ApprovalItemAvailability::availableQuantity($row) > 0)
                ->values();
        }

        $approvalItemsetIds = $approvalItems
            ->map(function ($approvalItem) {
                $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                return optional($itemSet)->id;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (!$includeApproval && $customerId > 0) {
            $approvalItemsetIds = ApprovalItem::with(['itemSet:id', 'legacyItemSet:id'])
                ->whereExists(function ($q) use ($companyId, $customerId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', $companyId)
                        ->where('approval_headers.customer_id', $customerId);
                })
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(TRIM(status)) in (?, ?, ?)', ['pending', 'sold', 'returned']);
                })
                ->get()
                ->filter(fn ($row) => \App\Services\ApprovalItemAvailability::availableQuantity($row) > 0)
                ->values()
                ->map(function ($approvalItem) {
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    return optional($itemSet)->id;
                })
                ->filter()
                ->unique()
                ->values()
                ->all();
        }

        $itemSets = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('is_final', 1)
            ->where('is_sold', 0)
            ->when(Schema::hasTable('sale_carts'), function ($q) use ($companyId, $request) {
                $this->excludeSaleCartLocks($q, (int) $companyId, (int) $request->user()->id);
            })
            ->when(!empty($approvalItemsetIds), function ($q) use ($approvalItemsetIds) {
                $q->whereNotIn('id', $approvalItemsetIds);
            })
            ->where(function ($q) use ($search) {
                $q->where('qr_code', 'like', '%' . $search . '%')
                    ->orWhere('HUID', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%')
                    ->orWhereHas('item', function ($q2) use ($search) {
                        $q2->where('item_name', 'like', '%' . $search . '%')
                            ->orWhere('item_code', 'like', '%' . $search . '%');
                    });
            })
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        $approvalRows = $approvalItems->map(function ($approvalItem) {
            $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
            $item = optional($itemSet)->item ?? $approvalItem->item;
            $gross = (float) ($approvalItem->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
            $otherWeight = (float) ($approvalItem->other_weight ?? optional($itemSet)->other ?? 0);
            $net = (float) ($approvalItem->net_weight ?? optional($itemSet)->net_weight ?? max(0, $gross - $otherWeight));
            $approvalPurity = (float) ($approvalItem->purity ?? 0);
            $itemPurity = (float) (optional($item)->outward_purity ?? 0);
            $purity = $approvalPurity > 0 ? $approvalPurity : $itemPurity;
            $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
            $savedNetPurity = (float) ($approvalItem->net_purity ?? 0);
            $netPurity = $savedNetPurity > 0 ? $savedNetPurity : max(0, $purity + $wastePercent);
            $savedFineWeight = (float) ($approvalItem->total_fine_weight ?? $approvalItem->fine_weight ?? 0);
            $fineWeight = $savedFineWeight > 0 ? $savedFineWeight : (($net * $netPurity) / 100);
            $metalRate = (float) ($approvalItem->metal_rate ?? 0);
            $savedMetalAmount = (float) ($approvalItem->metal_amount ?? 0);
            $metalAmount = $savedMetalAmount > 0 ? $savedMetalAmount : ($fineWeight * $metalRate);
            $labourRate = (float) ($approvalItem->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $savedLabourAmount = (float) ($approvalItem->labour_amount ?? 0);
            $labourAmount = $savedLabourAmount > 0 ? $savedLabourAmount : ($net * $labourRate);
            $otherAmount = (float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0);
            $savedTotalAmount = (float) ($approvalItem->total_amount ?? 0);
            $totalAmount = $savedTotalAmount > 0 ? $savedTotalAmount : ($metalAmount + $labourAmount + $otherAmount);
            $originalQty = max(1, (int) ($approvalItem->qty ?? 1));
            $availableQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
            $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem);
            if ($salePrice > 0) $totalAmount = $salePrice * $originalQty;

            foreach (['gross', 'otherWeight', 'net', 'fineWeight', 'metalAmount', 'labourAmount', 'otherAmount', 'totalAmount'] as $field) {
                $$field = \App\Services\ApprovalItemAvailability::remainingValue($$field, $originalQty, $availableQty);
            }

            return [
                'id' => (int) (optional($itemSet)->id ?? 0),
                'row_key' => 'approval_' . (int) $approvalItem->id,
                'itemset_id' => (int) (optional($itemSet)->id ?? 0),
                'approval_item_id' => (int) $approvalItem->id,
                'approval_id' => (int) ($approvalItem->approval_id ?? 0),
                'item_id' => (int) ($approvalItem->item_id ?? optional($itemSet)->item_id ?? 0),
                'name' => (string) (optional($item)->item_name ?? ''),
                'code' => (string) ($approvalItem->qr_code ?? optional($itemSet)->qr_code ?? ''),
                'huid' => (string) ($approvalItem->huid ?? optional($itemSet)->HUID ?? ''),
                'qty' => $availableQty,
                'quantity' => $availableQty,
                'available_quantity' => $availableQty,
                'gross_weight' => $gross,
                'other_weight' => $otherWeight,
                'net_weight' => $net,
                'purity' => $purity,
                'item_purity' => $itemPurity,
                'outward_purity' => $itemPurity,
                'waste_percent' => $wastePercent,
                'net_purity' => $netPurity,
                'fine_weight' => $fineWeight,
                'metal_rate' => $metalRate,
                'metal_amount' => $metalAmount,
                'labour_rate' => $labourRate,
                'labour_amount' => $labourAmount,
                'other_amount' => $otherAmount,
                'sale_price' => $salePrice,
                'total_amount' => $totalAmount,
                'remarks' => (string) ($approvalItem->remarks ?? ''),
                'other_charges' => $this->decodeOtherChargeDetails($approvalItem->other_charge_details ?? null),
                'is_item_only' => false,
                'source' => 'approval',
            ];
        })->values();

        $approvalItemIds = $approvalRows->pluck('item_id')->filter()->unique()->values()->all();
        $itemSetItemIds = $itemSets->pluck('item_id')->filter()->merge($approvalItemIds)->unique()->values()->all();

        $itemOnlyQuery = Item::query()
            ->where('company_id', $companyId)
            ->whereNotIn('id', $itemSetItemIds)
            ->where(function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_code', 'like', '%' . $search . '%');
            });

        if (Schema::hasColumn('items', 'is_active')) {
            $itemOnlyQuery->where('is_active', 1);
        }

        $itemOnly = $itemOnlyQuery
            ->orderBy('item_name')
            ->limit($limit)
            ->get(['id', 'item_name', 'item_code', 'outward_purity', 'labour_rate']);

        $itemSetRows = $itemSets->map(function ($set) {
            $gross = (float) ($set->gross_weight ?? 0);
            $otherWeight = (float) ($set->other ?? 0);
            $net = (float) ($set->net_weight ?? ($gross - $otherWeight));
            $purity = (float) (optional($set->item)->outward_purity ?? 0);
            $wastePercent = 0;
            $netPurity = $purity + $wastePercent;
            $fineWeight = $net * $netPurity / 100;
            $metalRate = 0;
            $metalAmount = $net * $metalRate;
            $labourRate = (float) ($set->sale_labour_rate ?? optional($set->item)->labour_rate ?? 0);
            $labourAmount = $net * $labourRate;
            $otherAmount = (float) ($set->sale_other ?? 0);
            $totalAmount = $metalAmount + $labourAmount + $otherAmount;

            return [
                'id' => (int) $set->id,
                'row_key' => 'set_' . (int) $set->id,
                'itemset_id' => (int) $set->id,
                'item_id' => (int) ($set->item_id ?? 0),
                'name' => (string) (optional($set->item)->item_name ?? ''),
                'code' => (string) ($set->qr_code ?? ''),
                'huid' => (string) ($set->HUID ?? ''),
                'qty' => 1,
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
                'sale_price' => $set->billing_sale_price,
                'total_amount' => (float) $set->billing_sale_price > 0 ? (float) $set->billing_sale_price : $totalAmount,
                'remarks' => '',
                'is_item_only' => false,
                'source' => 'itemset',
            ];
        })->values();

        $itemOnlyRows = $itemOnly->map(function ($item) {
            return [
                'id' => 0,
                'row_key' => 'item_' . (int) $item->id,
                'itemset_id' => 0,
                'item_id' => (int) $item->id,
                'name' => (string) ($item->item_name ?? ''),
                'code' => (string) ($item->item_code ?? ''),
                'huid' => '',
                'qty' => 1,
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
                'sale_price' => null,
                'total_amount' => 0,
                'remarks' => '',
                'is_item_only' => true,
                'source' => 'item',
            ];
        })->values();

        $resultRows = $approvalRows->concat($itemSetRows)->concat($itemOnlyRows)->values();
        if ($resultRows->isEmpty()) {
            $itemSetMatch = function ($q) use ($search) {
                $q->where('qr_code', 'like', '%' . $search . '%')
                    ->orWhere('HUID', 'like', '%' . $search . '%')
                    ->orWhere('barcode', 'like', '%' . $search . '%')
                    ->orWhereHas('item', function ($q2) use ($search) {
                        $q2->where('item_name', 'like', '%' . $search . '%')
                            ->orWhere('item_code', 'like', '%' . $search . '%');
                    });
            };
            $itemMatch = function ($q) use ($search) {
                $q->where('item_name', 'like', '%' . $search . '%')
                    ->orWhere('item_code', 'like', '%' . $search . '%');
            };

            $existsInOtherCompany = ItemSet::where('company_id', '!=', $companyId)
                ->where($itemSetMatch)
                ->exists()
                || Item::where('company_id', '!=', $companyId)
                    ->where($itemMatch)
                    ->exists();

            $existsInCompany = ItemSet::where('company_id', $companyId)
                ->where($itemSetMatch)
                ->exists()
                || Item::where('company_id', $companyId)
                    ->where($itemMatch)
                    ->exists();

            $message = 'No item found for this keyword.';
            if ($existsInOtherCompany) {
                $message = 'This item does not belong to your company.';
            } elseif ($existsInCompany) {
                $message = 'Item found but it is not available for sale.';
            }

            return response()->json([
                'success' => false,
                'message' => $message,
                'data' => [],
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $resultRows,
        ]);
    }

    public function addToCart(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'qr_code' => 'nullable|required_without_all:itemset_id,approval_item_id|string',
            'itemset_id' => 'nullable|required_without_all:qr_code,approval_item_id|integer',
            'approval_item_id' => 'nullable|required_without_all:qr_code,itemset_id|integer',
            'customer_id' => 'nullable|integer',
            'approval_customer_id' => 'nullable|integer',
            'approval_person_id' => 'nullable|integer',
            'approval_party_id' => 'nullable|integer',
            'qty' => 'nullable|integer|min:1',
        ]);

        $qrCode = trim((string) $request->qr_code);
        $requestedItemsetId = (int) $request->input('itemset_id', 0);
        $requestedApprovalItemId = (int) $request->input('approval_item_id', 0);
        $customerId = (int) $request->input('customer_id', 0);
        $approvalCustomerId = (int) (
            $request->input('approval_customer_id')
            ?? $request->input('approval_person_id')
            ?? $request->input('approval_party_id')
            ?? 0
        );
        $approvalLookupCustomerId = $approvalCustomerId > 0 ? $approvalCustomerId : $customerId;
        $hasSaleCartCustomerId = Schema::hasColumn('sale_carts', 'customer_id');
       
        if ($approvalLookupCustomerId > 0) {
            if (!Schema::hasColumn('sale_carts', 'approval_item_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale cart approval item column missing. Please run migration.'
                ], 500);
            }
            
            $approvalItem = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item', 'item'])
                ->whereExists(function ($q) use ($user, $approvalLookupCustomerId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                        ->where('approval_headers.customer_id', $approvalLookupCustomerId);
                })
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(TRIM(status)) in (?, ?, ?)', ['pending', 'sold', 'returned']);
                })
                ->when($requestedApprovalItemId > 0, function ($q) use ($requestedApprovalItemId) {
                    $q->where('id', $requestedApprovalItemId);
                })
                ->when($requestedApprovalItemId <= 0 && $requestedItemsetId > 0, function ($q) use ($requestedItemsetId) {
                    $q->where(function ($match) use ($requestedItemsetId) {
                        $match->where('itemset_id', $requestedItemsetId)
                            ->orWhere('item_id', $requestedItemsetId);
                    });
                })
                ->when($requestedApprovalItemId <= 0 && $requestedItemsetId <= 0, function ($q) use ($qrCode) {
                    $q->whereRaw('TRIM(qr_code) = ?', [$qrCode]);
                })
                ->latest('id')
                ->first();
                 
            if ($approvalItem) {
                $availableQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
                $requestedQty = max(1, (int) $request->input('qty', 1));
                if ($availableQty <= 0 || $requestedQty > $availableQty) {
                    return response()->json([
                        'success' => false,
                        'message' => "Only {$availableQty} piece(s) remain in this approval item.",
                        'available_quantity' => $availableQty,
                    ], 422);
                }

                $otherLock = $this->activeSaleCartLockForApprovalItem(\App\Services\CompanyBranches::apiManagementCompanyId(request()), (int) $approvalItem->id);
                if ($otherLock && (int) $otherLock->user_id !== (int) $user->id) {
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    return $this->saleCartLockedResponseForApproval($approvalItem, $itemSet, $otherLock);
                }

                $exists = SaleCart::where('user_id', $user->id)
                    ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                    ->where('approval_item_id', $approvalItem->id)
                    ->first();

                if ($exists) {
                    $existingCustomerId = $hasSaleCartCustomerId ? (int) ($exists->customer_id ?? 0) : 0;
                    if ($hasSaleCartCustomerId && $customerId > 0 && $existingCustomerId > 0 && $existingCustomerId !== $customerId) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Product already selected for another sale customer. Please remove it from that cart first.',
                            'code' => 'SALE_CART_CUSTOMER_MISMATCH',
                            'cart_id' => (int) $exists->id,
                            'customer_id' => $existingCustomerId,
                            'approval_customer_id' => $approvalLookupCustomerId,
                        ], 409);
                    }

                    if (Schema::hasColumn('sale_carts', 'qty') && $request->filled('qty')) {
                        $exists->update(['qty' => $requestedQty]);
                    }

                    return response()->json([
                        'success' => true,
                        'message' => 'Product already added to cart',
                        'already_added' => true,
                        'source' => 'approval',
                        'cart_id' => (int) $exists->id,
                        'customer_id' => $hasSaleCartCustomerId ? $exists->customer_id : $customerId,
                        'approval_customer_id' => $approvalLookupCustomerId,
                        'approval_item' => $approvalItem,
                    ]);
                }

                $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                $cartPayload = [
                    'user_id' => $user->id,
                    'company_id' => \App\Services\CompanyBranches::apiManagementCompanyId(request()),
                    'itemset_id' => optional($itemSet)->id,
                    'qty' => $requestedQty,
                    'approval_item_id' => $approvalItem->id,
                ];

                if ($hasSaleCartCustomerId) {
                    $cartPayload['customer_id'] = $customerId;
                }

                $cart = SaleCart::create($cartPayload);

                return response()->json([
                    'success' => true,
                    'message' => 'Approval product added to cart',
                    'source' => 'approval',
                    'cart_id' => (int) $cart->id,
                    'customer_id' => $hasSaleCartCustomerId ? $cart->customer_id : $customerId,
                    'approval_customer_id' => $approvalLookupCustomerId,
                    'approval_item' => $approvalItem,
                ]);
            }

            if ($requestedApprovalItemId > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Approval item not found or already added/sold.',
                ], 422);
            }
        }

        return DB::transaction(function () use ($user, $qrCode, $requestedItemsetId, $customerId, $hasSaleCartCustomerId) {
            $item = ItemSet::with('item')
                ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                ->when($requestedItemsetId > 0, function ($q) use ($requestedItemsetId) {
                    $q->where('id', $requestedItemsetId);
                })
                ->when($requestedItemsetId <= 0, function ($q) use ($qrCode) {
                    $q->where(function ($match) use ($qrCode) {
                        $match->where('qr_code', $qrCode)
                            ->orWhere('barcode', $qrCode)
                            ->orWhere('HUID', $qrCode);
                    });
                })
                ->where('is_sold', 0)
                ->lockForUpdate()
                ->first();

            if (!$item) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item not found or already sold'
                ]);
            }

            $existingLock = $this->activeSaleCartLockForItemSet(\App\Services\CompanyBranches::apiManagementCompanyId(request()), (int) $item->id);
            if ($existingLock && (int) $existingLock->user_id !== (int) $user->id) {
                return $this->saleCartLockedResponse($item, $existingLock);
            }

            $exists = SaleCart::where('user_id', $user->id)
                ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                ->where('itemset_id', $item->id)
                ->first();

            if ($exists) {
                $existingCustomerId = $hasSaleCartCustomerId ? (int) ($exists->customer_id ?? 0) : 0;
                if ($hasSaleCartCustomerId && $customerId > 0 && $existingCustomerId > 0 && $existingCustomerId !== $customerId) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product already selected for another sale customer. Please remove it from that cart first.',
                        'code' => 'SALE_CART_CUSTOMER_MISMATCH',
                        'cart_id' => (int) $exists->id,
                        'customer_id' => $existingCustomerId,
                    ], 409);
                }

                $nextQty = max(1, (int) ($exists->qty ?? 1)) + 1;
                $availableQty = max(0, (int) ($item->available_quantity ?? 0));
                if ($nextQty > $availableQty) {
                    return response()->json(['success' => false, 'message' => "Only {$availableQty} piece(s) remain for this QR label."], 422);
                }
                $exists->update(['qty' => $nextQty]);

                return response()->json([
                    'success' => true,
                    'message' => 'Scanned piece added to cart quantity',
                    'already_added' => true,
                    'qty' => $nextQty,
                    'source' => 'stock',
                    'cart_id' => (int) $exists->id,
                    'customer_id' => $hasSaleCartCustomerId ? $exists->customer_id : ($customerId > 0 ? $customerId : null),
                    'item' => $item,
                ]);
            }

            $cartPayload = [
                'user_id'    => $user->id,
                'company_id' => \App\Services\CompanyBranches::apiManagementCompanyId(request()),
                'itemset_id' => $item->id,
                'qty' => 1,
            ];

            if ($hasSaleCartCustomerId && $customerId > 0) {
                $cartPayload['customer_id'] = $customerId;
            }

            $cart = SaleCart::create($cartPayload);

            return response()->json([
                'success' => true,
                'message' => 'Product added to cart',
                'cart_id' => (int) $cart->id,
                'customer_id' => $hasSaleCartCustomerId ? $cart->customer_id : ($customerId > 0 ? $customerId : null),
                'item' => $item
            ]);
        });
    }

    private function approvalCartQuantityValues($cart, $approvalItem, $itemSet, $item): array
    {
        $originalQty = max(1, (int) ($approvalItem->qty ?? 1));
        $qty = max(1, (int) ($cart->qty ?? 1));
        $scale = $qty / $originalQty;
        // Approval totals describe the issued quantity, not the original stock batch.
        $gross = (float) ($approvalItem->gross_weight ?? optional($itemSet)->gross_weight ?? 0);
        $other = (float) ($approvalItem->other_weight ?? optional($itemSet)->other ?? 0);
        $net = (float) ($approvalItem->net_weight ?? optional($itemSet)->net_weight ?? max(0, $gross - $other));
        $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0);
        $waste = (float) ($approvalItem->waste_percent ?? 0);
        $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity + $waste));
        $fine = (float) ($approvalItem->total_fine_weight ?? ($net * $netPurity / 100)) * $scale;
        $metal = (float) ($approvalItem->metal_amount ?? 0) * $scale;
        $labour = (float) ($approvalItem->labour_amount ?? 0) * $scale;
        $otherAmount = (float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0) * $scale;
        $total = $approvalItem->total_amount !== null ? (float) $approvalItem->total_amount * $scale : $metal + $labour + $otherAmount;
        $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem);
        if ((float) $salePrice > 0) {
            $total = (float) $salePrice * $qty;
            $fine = $metal = $labour = $otherAmount = 0;
        }
        return [
            'qty'=>$qty, 'quantity'=>$qty, 'approval_quantity'=>$originalQty,
            'gross_weight'=>number_format($gross * $scale, 3, '.', ''),
            'other_weight'=>number_format($other * $scale, 3, '.', ''),
            'net_weight'=>number_format($net * $scale, 3, '.', ''),
            'purity'=>number_format($purity, 3, '.', ''),
            'waste_percent'=>number_format($waste, 3, '.', ''),
            'net_purity'=>number_format($netPurity, 3, '.', ''),
            'fine_weight'=>number_format($fine, 3, '.', ''),
            'metal_rate'=>number_format((float) ($approvalItem->metal_rate ?? 0), 2, '.', ''),
            'metal_amount'=>number_format($metal, 2, '.', ''),
            'labour_rate'=>number_format((float) ($approvalItem->labour_rate ?? 0), 2, '.', ''),
            'labour_amount'=>number_format($labour, 2, '.', ''),
            'other_amount'=>number_format($otherAmount, 2, '.', ''),
            'total_amount'=>number_format($total, 2, '.', ''), 'sale_price'=>$salePrice,
        ];
    }

    public function cartItems(Request $request)
    {
        $user = auth()->user();
        $customerId = (int) $request->input('customer_id', 0);
        $hasSaleCartCustomerId = Schema::hasColumn('sale_carts', 'customer_id');

        $items = SaleCart::with([
                'itemset.item',
                'approvalItem.approval.customer',
                'approvalItem.itemSet.item',
                'approvalItem.legacyItemSet.item',
                'approvalItem.item',
            ])
            ->where('user_id', $user->id)
            ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
            ->when($hasSaleCartCustomerId && $customerId > 0, function ($query) use ($customerId) {
                $query->where('customer_id', $customerId);
            })
            ->get()
            ->map(function ($cart) use ($hasSaleCartCustomerId) {
                if ($cart->approvalItem) {
                    $approvalItem = $cart->approvalItem;
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    $item = optional($itemSet)->item ?? $approvalItem->item;
                    $quantityValues = $this->approvalCartQuantityValues($cart, $approvalItem, $itemSet, $item);
                    $availableQuantity = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);

                    return array_merge([
                        'id' => $cart->id,
                        'source' => 'approval',
                        'customer_id' => $hasSaleCartCustomerId ? ($cart->customer_id ?? optional($approvalItem->approval)->customer_id) : optional($approvalItem->approval)->customer_id,
                        'approval_item_id' => $approvalItem->id,
                        'approval_id' => $approvalItem->approval_id,
                        'itemset_id' => optional($itemSet)->id,
                        'item_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                        'qr_code' => $approvalItem->qr_code ?? optional($itemSet)->qr_code,
                        'huid' => $approvalItem->huid ?? optional($itemSet)->HUID,
                        'item_name' => optional($item)->item_name,
                        'available_quantity' => $availableQuantity,
                    ], $quantityValues);
                }

                $row = $cart->toArray();
                $row['source'] = 'stock';
                $row['cart_id'] = (int) $cart->id;
                $row['customer_id'] = $hasSaleCartCustomerId ? $cart->customer_id : null;

                return $row;
            })
            ->values();

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
            ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
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
        $perPage = max(1, min((int) $request->input('per_page', 100), 500));
        $includeQrImage = $request->boolean('include_qr_image', true);

        $query = ItemSet::with('item:id,item_name')
            ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
            ->where('is_final', 1)
            ->whereNotNull('qr_code');

        if ($request->filled('item_id')) {
            $query->where('item_id', (int) $request->item_id);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                $q->where('qr_code', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%")
                    ->orWhere('HUID', 'like', "%{$search}%")
                    ->orWhereHas('item', function ($itemQuery) use ($search) {
                        $itemQuery->where('item_name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('from_date') && $request->filled('to_date')) {
            $query->whereDate('created_at', '>=', $request->from_date)
                ->whereDate('created_at', '<=', $request->to_date);
        }

        $paginator = $query
            ->select([
                'id',
                'company_id',
                'item_id',
                'serial_no',
                'qr_code',
                'barcode',
                'HUID',
                'gross_weight',
                'other',
                'net_weight',
                'quantity',
                'available_quantity',
                'sale_labour_formula',
                'sale_labour_rate',
                'sale_labour_amount',
                'sale_other',
                'sale_price',
                'wholesale_price',
                'is_printed',
                'created_at',
                'printed_at',
            ])
            ->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc(DB::raw('COALESCE(printed_at, created_at)'))
            ->orderByDesc('serial_no')
            ->orderByDesc('id')
            ->paginate($perPage);

        $rows = $paginator->getCollection()->map(function ($set) use ($includeQrImage) {
            $row = [
                'id' => $set->id,
                'item_id' => (int) $set->item_id,
                'item_name' => $set->item ? $set->item->item_name : 'N/A',
                'serial_no' => $set->serial_no,
                'qr_code' => $set->qr_code,
                'barcode' => $set->barcode,
                'huid' => $set->HUID,
                'gross_weight' => (float) ($set->gross_weight ?? 0),
                'other_weight' => (float) ($set->other ?? 0),
                'net_weight' => (float) ($set->net_weight ?? 0),
                'quantity' => max(1, (int) ($set->quantity ?? 1)),
                'available_quantity' => max(0, (int) ($set->available_quantity ?? ($set->is_sold ? 0 : ($set->quantity ?? 1)))),
                'sale_labour_formula' => $set->sale_labour_formula ?? null,
                'labour_rate' => (float) ($set->sale_labour_rate ?? 0),
                'labour_amount' => (float) ($set->sale_labour_amount ?? 0),
                'sale_other' => (float) ($set->sale_other ?? 0),
                'sale_price' => $set->billing_sale_price,
                'is_printed' => (int) ($set->is_printed ?? 0),
                'created_at' => $set->created_at ? \Carbon\Carbon::parse($set->created_at)->format('d-m-Y h:i A') : null,
                'printed_at' => $set->printed_at ? \Carbon\Carbon::parse($set->printed_at)->format('d-m-Y h:i A') : null,
            ];

            if ($includeQrImage) {
                $builder = new Builder(
                    writer: new PngWriter(),
                    data: $set->qr_code,
                    size: 120,
                    margin: 5
                );

                $row['qr_image'] = 'data:image/png;base64,' . base64_encode($builder->build()->getString());
            }

            return $row;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
            'pagination' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
                'has_more' => $paginator->hasMorePages(),
            ],
            'qr_image_included' => $includeQrImage,
        ]);
    }

    public function downloadQrPdf(Request $request)
    {
        return $this->sharedLabelPdf($request, 'download');
    }

    public function directQrPdfForApp(Request $request)
    {
        return $this->sharedLabelPdf($request, 'stream');
    }

    private function sharedLabelPdf(Request $request, string $action)
    {
        $ids = $request->input('ids', []);
        if (is_string($ids)) {
            $ids = array_values(array_filter(array_map('trim', explode(',', $ids)), fn ($id) => $id !== ''));
        }
        if (is_array($ids)) $ids = array_values(array_unique($ids));
        $printRequest = clone $request;
        $printRequest->merge([
            'ids' => $ids,
            // Legacy app/Postman requests omit the format; match Direct Print's two halves.
            'label_format' => $this->resolveLabelFormat((string) $request->input('label_format', 'double_details')),
            'start_position' => $this->resolveStartPosition($request->input('start_position', 1)),
        ]);
        return app(ItemLabelPrintController::class)->renderPdf($printRequest, $action);
    }

    private function resolveLabelFormat(string $labelFormat): string
    {
        return \App\Services\LabelPrintFormat::resolve($labelFormat);
    }

    private function resolveStartPosition($value): int
    {
        $position = (int) $value;
        if ($position < 1) {
            return 1;
        }
        if ($position > 22) {
            return 22;
        }
        return $position;
    }

    private function buildPrintPages($itemSets, int $startPosition)
    {
        $labels = $itemSets->values();
        $total = $labels->count();
        $cursor = 0;
        $slotStart = max(1, min(22, $startPosition));
        $pages = collect();

        if ($total === 0) {
            return collect([collect(array_fill(0, 22, null))]);
        }

        while ($cursor < $total) {
            $slots = array_fill(0, 22, null);
            for ($slot = $slotStart - 1; $slot < 22 && $cursor < $total; $slot++) {
                $slots[$slot] = $labels->get($cursor);
                $cursor++;
            }
            $pages->push(collect($slots));
            $slotStart = 1;
        }

        return $pages;
    }

    public function confirmSale(Request $request)
    {
        DB::beginTransaction();

        try {
            $user = auth()->user();
            $companyId = \App\Services\CompanyBranches::apiManagementCompanyId(request());

            $request->validate([
                'customer_id' => 'required|integer',
                'remarks' => 'nullable|string',
                'received_amount' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|max:30',
                'payment_reference' => 'nullable|string|max:120',
                'payment_note' => 'nullable|string|max:255',
            'show_previous_bills' => 'sometimes|boolean',
            ]);

            $customerExists = Customer::where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                ->where('id', (int) $request->customer_id)
                ->saleParties()
                ->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer for this company',
                ], 422);
            }

            $hasSaleCartCustomerId = Schema::hasColumn('sale_carts', 'customer_id');
            $customerId = (int) $request->customer_id;

            $cartQuery = SaleCart::where('user_id', auth()->id())
                ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                ->when($hasSaleCartCustomerId, function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                });

            $cartItems = $cartQuery->get();

            if ($cartItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart empty']);
            }

            $sale = Sale::create([
                'company_id'  => \App\Services\CompanyBranches::apiManagementCompanyId(request()),
                'customer_id' => $request->customer_id,
                'voucher_no'  => \App\Services\VoucherSeries::next(\App\Services\CompanyBranches::apiManagementCompanyId($request), 'SL'),
                'sale_date'   => now(),
                'remarks' => $request->input('remarks', $request->input('remark')),
                'net_total'   => 0,
                'received_amount' => (float) $request->input('received_amount', 0),
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
                'use_silver_balance' => $request->boolean('use_silver_balance'),
                'show_previous_bills' => $request->boolean('show_previous_bills'),
                'employee_id' => $user->id,
                'modified_count' => 0,
            ]);

            $initialReceived = (float) $request->input('received_amount', 0);
            if ($initialReceived > 0) {
                SalePayment::create([
                    'company_id' => $companyId,
                    'sale_id' => $sale->id,
                    'amount' => $initialReceived,
                    'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                    'payment_mode' => $request->input('payment_mode'),
                    'payment_reference' => $request->input('payment_reference'),
                    'payment_note' => $request->input('payment_note'),
                    'created_by' => $user->id,
                ]);
            }

            $total = 0;

            foreach ($cartItems as $cart) {
                if (!empty($cart->approval_item_id)) {
                    $approvalOwner = $this->resolveApprovalItemForSale(
                        $companyId,
                        (int) $cart->approval_item_id,
                        null,
                        null,
                        false
                    );
                    $approvalOwnerCustomerId = (int) optional($approvalOwner?->approval)->customer_id;

                    $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                        \App\Services\CompanyBranches::apiManagementCompanyId(request()),
                        (int) $cart->approval_item_id,
                        $approvalOwnerCustomerId > 0 ? $approvalOwnerCustomerId : null
                    )->load(['itemSet.item', 'legacyItemSet.item', 'item']);

                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    $item = optional($itemSet)->item ?? $approvalItem->item;
                    $originalQty = max(1, (int) ($approvalItem->qty ?? 1));
                    $qty = max(1, (int) ($cart->qty ?? 1));
                    $availableQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
                    if ($qty > $availableQty) {
                        throw new \RuntimeException("Only {$availableQty} piece(s) remain in this approval item.");
                    }
                    // Approval row weights and amounts are stored as row totals. Convert to
                    // per-piece values, then save only the quantity actually being sold.
                    $scale = $qty / $originalQty;
                    $gross = (float) ($approvalItem->gross_weight ?? optional($itemSet)->gross_weight ?? 0) / $originalQty;
                    $otherWeight = (float) ($approvalItem->other_weight ?? optional($itemSet)->other ?? 0) / $originalQty;
                    $net = (float) ($approvalItem->net_weight ?? optional($itemSet)->net_weight ?? max(0, $gross - $otherWeight) * $originalQty) / $originalQty;
                    $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0);
                    $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
                    $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity + $wastePercent));
                    $fineWeight = (float) ($approvalItem->total_fine_weight ?? (($net * $originalQty * $netPurity) / 100)) / $originalQty;
                    $metalAmount = (float) ($approvalItem->metal_amount ?? 0) / $originalQty;
                    $labourAmount = (float) ($approvalItem->labour_amount ?? 0) / $originalQty;
                    $otherAmount = (float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0) / $originalQty;
                    $lineTotal = (float) ($approvalItem->total_amount ?? (($metalAmount + $labourAmount + $otherAmount) * $originalQty)) * $scale;
                    $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem);
                    if ($salePrice > 0) {
                        $lineTotal = $salePrice * $qty;
                        $fineWeight = $metalAmount = $labourAmount = $otherAmount = 0;
                    }

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'itemset_id' => optional($itemSet)->id,
                        'product_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                        'approval_item_id' => $approvalItem->id,
                        'qty' => $qty,
                        'sale_price' => $salePrice > 0 ? $salePrice : null,
                        'gross_weight' => $gross * $qty,
                        'other_weight' => $otherWeight * $qty,
                        'net_weight' => $net * $qty,
                        'purity' => $purity,
                        'waste_percent' => $wastePercent,
                        'net_purity' => $netPurity,
                        'fine_weight' => $fineWeight * $qty,
                        'metal_rate' => (float) ($approvalItem->metal_rate ?? 0),
                        'metal_amount' => $metalAmount * $qty,
                        'labour_rate' => (float) ($approvalItem->labour_rate ?? 0),
                        'labour_amount' => $labourAmount * $qty,
                        'other_amount' => $otherAmount * $qty,
                        'other_charge_details' => $this->normalizeOtherChargeDetails($approvalItem->other_charge_details ?? null),
                        'total_amount' => $lineTotal,
                        'remarks' => $approvalItem->remarks ?? null,
                    ]);

                    \App\Services\ApprovalItemAvailability::syncStatus($approvalItem->fresh());
                    $this->refreshApprovalHeaderStatus([(int) $approvalItem->approval_id]);
                    // The approval issue already deducted these pieces from batch availability.

                    $total += $lineTotal;
                    continue;
                }

                $item = TransactionItemGuard::lockItemSetForUse(\App\Services\CompanyBranches::apiManagementCompanyId(request()), (int) $cart->itemset_id)->load('item');
                $qty = max(1, (int) ($cart->qty ?? 1));
                $availableQty = max(0, (int) ($item->available_quantity ?? ($item->is_sold ? 0 : 1)));
                if ($qty > $availableQty) {
                    throw new \RuntimeException("Only {$availableQty} piece(s) remain for QR label {$item->qr_code}.");
                }
                $batchQty = max(1, (int) ($item->quantity ?? 1));
                $purity = (float) (optional($item->item)->outward_purity ?? 0);
                $gross = (float) $item->gross_weight / $batchQty;
                $otherWeight = (float) ($item->other ?? 0) / $batchQty;
                $net = (float) ($item->net_weight ?? 0) / $batchQty;
                $labourAmount = (float) ($item->sale_labour_amount ?? (($item->net_weight ?? 0) * ($item->sale_labour_rate ?? 0))) / $batchQty;
                $otherAmount = (float) ($item->sale_other ?? 0);
                $salePrice = (float) ($item->billing_sale_price ?? 0);
                $lineTotal = $salePrice > 0 ? $salePrice * $qty : ($labourAmount + $otherAmount) * $qty;

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'itemset_id' => $item->id,
                    'qty' => $qty,
                    'sale_price' => $salePrice > 0 ? $salePrice : null,
                    'gross_weight' => $gross * $qty,
                    'other_weight' => $otherWeight * $qty,
                    'net_weight' => $net * $qty,
                    'purity' => $purity,
                    'waste_percent' => 0,
                    'net_purity' => $purity,
                    'fine_weight' => $net * ($purity / 100) * $qty,
                    'metal_rate' => 0,
                    'metal_amount' => 0,
                    'labour_rate' => $item->sale_labour_rate ?? 0,
                    'labour_amount' => $salePrice > 0 ? 0 : $labourAmount * $qty,
                    'other_amount' => $salePrice > 0 ? 0 : $otherAmount * $qty,
                    'other_charge_details' => $this->normalizeOtherChargeDetails($item['other_charge_details'] ?? $item['other_charges'] ?? null),
                    'total_amount' => $lineTotal,
                    'remarks' => $item->remarks ?? null,
                ]);

                \App\Services\BatchInventory::consume($item, $qty);
                $total += $lineTotal;
            }

            $sale->update(['net_total' => $this->saleCashPayableTotal($sale->fresh('saleItems.itemset.item', 'saleItems.product'))]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale,
                (bool) $sale->use_silver_balance,
                (int) $user->id
            );

            SaleCart::where('user_id', auth()->id())
                ->where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId(request()))
                ->when($hasSaleCartCustomerId, function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                })
                ->delete();

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $this->saleResponsePayload($sale->fresh(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $status = str_contains($e->getMessage(), 'remain in this approval item') ? 422 : 500;
            return response()->json(['success' => false, 'message' => $e->getMessage()], $status);
        }
    }

    public function getItemByQr(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $code = trim((string) ($request->input('qr_code') ?? $request->input('code') ?? $request->input('barcode') ?? $request->input('label_code') ?? ''));

        if ($code === '') {
            return response()->json([
                'success' => false,
                'message' => 'QR code is required.'
            ], 422);
        }

        $item = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where(function ($q) use ($code) {
                $q->where('qr_code', $code)
                    ->orWhere('barcode', $code)
                    ->orWhere('HUID', $code);
            })
            ->first();

        if (!$item) {
            $otherCompanyItem = ItemSet::where(function ($q) use ($code) {
                $q->where('qr_code', $code)
                    ->orWhere('barcode', $code)
                    ->orWhere('HUID', $code);
            })->first();

            return response()->json([
                'success' => false,
                'message' => $otherCompanyItem
                    ? 'Item belongs to another company.'
                    : 'Item not found for this company.'
            ], 404);
        }

        $pendingApprovalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->where(function ($q) use ($item) {
                $q->where('itemset_id', (int) $item->id)
                    ->orWhere('item_id', (int) $item->id);
            })
            // Match the web approval picker: legacy and partial workflow
            // statuses remain eligible when the linked quantities show an
            // available balance. Only terminal/cancelled rows are excluded.
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) not in (?, ?, ?, ?)', [
                        'cancelled', 'canceled', 'void', 'deleted',
                    ]);
            })
            ->first();

        if ($pendingApprovalItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item is in approval. Use Add Label from Approval.',
            ], 422);
        }

        if ((int) $item->is_sold === 1) {
            $saleItem = SaleItem::with('sale')
                ->where('itemset_id', $item->id)
                ->latest('id')
                ->first();

            if ($saleItem?->sale) {
                return response()->json([
                    'success' => false,
                    'message' => 'Item already used in sale voucher: ' . ($saleItem->sale->voucher_no ?? $saleItem->sale->id),
                ], 422);
            }

            return response()->json([
                'success' => false,
                'message' => 'Item is not available in stock.',
            ], 422);
        }

        $existingLock = $this->activeSaleCartLockForItemSet((int) $companyId, (int) $item->id);
        if ($existingLock && (int) $existingLock->user_id !== (int) $request->user()->id) {
            return $this->saleCartLockedResponse($item, $existingLock);
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
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $customerId = (int) (
            $request->input('approval_customer_id')
            ?? $request->input('approval_person_id')
            ?? $request->input('approval_party_id')
            ?? $request->input('customer_id')
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

        $rows = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item', 'item'])
            ->whereExists(function ($q) use ($companyId, $customerId) {
                $q->select(DB::raw(1))
                    ->from('approval_headers')
                    ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                    ->where('approval_headers.company_id', $companyId)
                    ->where('approval_headers.customer_id', $customerId);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) not in (?, ?, ?, ?)', [
                        'cancelled', 'canceled', 'void', 'deleted',
                    ]);
            })
            ->get()
            ->filter(fn ($row) => \App\Services\ApprovalItemAvailability::availableQuantity($row) > 0)
            ->values()
            ->map(function ($row) {
                $itemSet = $row->itemSet ?? $row->legacyItemSet;
                $item = optional($itemSet)->item ?? $row->item;
                $originalQty = max(1, (int) ($row->qty ?? 1));
                $availableQty = \App\Services\ApprovalItemAvailability::availableQuantity($row);
                $gross = (float) ($row->gross_weight ?? 0);
                $otherWeight = (float) ($row->other_weight ?? 0);
                $net = (float) ($row->net_weight ?? ($gross - $otherWeight));
                $approvalPurity = (float) ($row->purity ?? 0);
                $itemPurity = (float) (optional($item)->outward_purity ?? 0);
                $purity = $approvalPurity > 0 ? $approvalPurity : $itemPurity;
                $wastePercent = (float) ($row->waste_percent ?? 0);
                $savedNetPurity = (float) ($row->net_purity ?? 0);
                $netPurity = $savedNetPurity > 0 ? $savedNetPurity : ($purity + $wastePercent);
                $savedFineWeight = (float) ($row->total_fine_weight ?? $row->fine_weight ?? 0);
                $fineWeight = $savedFineWeight > 0 ? $savedFineWeight : ($net * $netPurity / 100);
                $metalRate = (float) ($row->metal_rate ?? 0);
                $savedMetalAmount = (float) ($row->metal_amount ?? 0);
                $metalAmount = $savedMetalAmount > 0 ? $savedMetalAmount : ($fineWeight * $metalRate);
                $labourRate = (float) ($row->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
                $savedLabourAmount = (float) ($row->labour_amount ?? 0);
                $labourAmount = $savedLabourAmount > 0 ? $savedLabourAmount : ($net * $labourRate);
                $otherAmount = (float) ($row->other_amount ?? optional($itemSet)->sale_other ?? 0);
                $savedTotalAmount = (float) ($row->total_amount ?? 0);
                $totalAmount = $savedTotalAmount > 0 ? $savedTotalAmount : ($metalAmount + $labourAmount + $otherAmount);
                $salePrice = \App\Services\ApprovalItemPricing::unitSalePrice($row);
                if ($salePrice > 0) $totalAmount = $salePrice * $originalQty;

                // Keep row totals for display, and expose per-piece values for
                // clients that let the user sell only part of this approval row.
                $unitValues = [
                    'unit_gross_weight' => $gross / $originalQty,
                    'unit_other_weight' => $otherWeight / $originalQty,
                    'unit_net_weight' => $net / $originalQty,
                    'unit_fine_weight' => $fineWeight / $originalQty,
                    'unit_metal_amount' => $metalAmount / $originalQty,
                    'unit_labour_amount' => $labourAmount / $originalQty,
                    'unit_other_amount' => $otherAmount / $originalQty,
                    'unit_total_amount' => $totalAmount / $originalQty,
                    'unit_sale_price' => $salePrice,
                ];

                foreach (['gross', 'otherWeight', 'net', 'fineWeight', 'metalAmount', 'labourAmount', 'otherAmount', 'totalAmount'] as $field) {
                    $$field = \App\Services\ApprovalItemAvailability::remainingValue($$field, $originalQty, $availableQty);
                }

                $soldQty = min($originalQty, \App\Services\ApprovalItemAvailability::soldQuantity($row));
                $returnedQty = min($originalQty, \App\Services\ApprovalItemAvailability::returnedQuantity($row));

                return [
                    'approval_item_id' => $row->id,
                    'approval_id' => $row->approval_id,
                    'approval_customer_id' => optional($row->approval)->customer_id,
                    'approval_customer_name' => optional(optional($row->approval)->customer)->name,
                    'itemset_id' => (int) (optional($itemSet)->id ?? 0),
                    'item_id' => (int) (optional($itemSet)->item_id ?? $row->item_id ?? 0),
                    'qty' => $availableQty,
                    'quantity' => $availableQty,
                    'available_quantity' => $availableQty,
                    'pending_quantity' => $availableQty,
                    'original_quantity' => $originalQty,
                    'sold_quantity' => $soldQty,
                    'returned_quantity' => $returnedQty,
                    'unit_values' => $unitValues,
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
                    'item_purity' => $itemPurity,
                    'outward_purity' => $itemPurity,
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
                    'sale_price' => $salePrice,
                    'total_amount' => $totalAmount,
                    'total_amt' => $totalAmount,
                    'other_charges' => $this->decodeOtherChargeDetails($row->other_charge_details ?? null),
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
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

        $query = ItemSet::with('item')
            ->where('company_id', $companyId)
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

        $pendingApprovalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
            $q->where('company_id', $companyId);
        })
            ->where(function ($q) use ($item) {
                $q->where('itemset_id', (int) $item->id)
                    ->orWhere('item_id', (int) $item->id);
            })
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
            ->first();

        if ($pendingApprovalItem) {
            return response()->json([
                'success' => false,
                'message' => 'Item is in approval. Use Add Label from Approval.',
            ], 422);
        }

        $existingLock = $this->activeSaleCartLockForItemSet((int) $companyId, (int) $item->id);
        if ($existingLock && (int) $existingLock->user_id !== (int) $request->user()->id) {
            return $this->saleCartLockedResponse($item, $existingLock);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId(request());
        $customerId = (int) (
            $request->input('customer_id')
            ?? $request->input('customer')
            ?? $request->input('party_id')
            ?? 0
        );
        $approvalCustomerId = (int) (
            $request->input('approval_customer_id')
            ?? $request->input('approval_person_id')
            ?? $request->input('approval_party_id')
            ?? 0
        );

        $request->validate([
            'items' => 'required|array|min:1',
            'remarks' => 'nullable|string',
            'received_amount' => 'nullable|numeric|min:0',
            'payment_mode' => 'nullable|string|max:30',
            'payment_reference' => 'nullable|string|max:120',
            'payment_note' => 'nullable|string|max:255',
            'show_previous_bills' => 'sometimes|boolean',
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
            ->saleParties()
            ->exists();
        if (!$customerExists) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid customer for this company.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $sale = Sale::create([
                'company_id'  => $companyId,
                'customer_id' => $customerId,
                'voucher_no'  => \App\Services\VoucherSeries::next(\App\Services\CompanyBranches::apiManagementCompanyId($request), 'SL'),
                'sale_date'   => now(),
                'remarks' => $request->input('remarks', $request->input('remark')),
                'net_total'   => 0,
                'received_amount' => (float) $request->input('received_amount', 0),
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
                'use_silver_balance' => $request->boolean('use_silver_balance'),
                'show_previous_bills' => $request->boolean('show_previous_bills'),
                'employee_id' => $user->id,
                'modified_count' => 0,
            ]);

            $initialReceived = (float) $request->input('received_amount', 0);
            if ($initialReceived > 0) {
                SalePayment::create([
                    'company_id' => $companyId,
                    'sale_id' => $sale->id,
                    'amount' => $initialReceived,
                    'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                    'payment_mode' => $request->input('payment_mode'),
                    'payment_reference' => $request->input('payment_reference'),
                    'payment_note' => $request->input('payment_note'),
                    'created_by' => $user->id,
                ]);
            }

            $total = 0;
            $approvalIds = [];
            $soldItemsetIds = [];

            foreach ($request->items as $item) {
                $itemsetId = (int) ($item['itemset_id'] ?? $item['id'] ?? 0);
                $productId = (int) ($item['item_id'] ?? $item['product_id'] ?? 0);
                $approvalItemId = (int) ($item['approval_item_id'] ?? 0);
                $rowApprovalCustomerId = (int) (
                    $item['approval_customer_id']
                    ?? $item['approval_person_id']
                    ?? $item['approval_party_id']
                    ?? $approvalCustomerId
                    ?? 0
                );
                $approvalItem = null;

                if ($itemsetId <= 0 && $productId <= 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Each row requires itemset_id or item_id.',
                    ], 422);
                }

                if ($approvalItemId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        $approvalItemId,
                        $itemsetId > 0 ? $itemsetId : null,
                        $rowApprovalCustomerId > 0 ? $rowApprovalCustomerId : null,
                        false
                    );

                    if (!$approvalItem) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approval item not found or already sold for selected approval person.',
                        ], 422);
                    }

                    $approvalItemId = (int) $approvalItem->id;
                    $resolvedApprovalCustomerId = $rowApprovalCustomerId > 0
                        ? $rowApprovalCustomerId
                        : (int) optional($approvalItem->approval)->customer_id;
                    $approvalCartLock = $this->activeSaleCartLockForApprovalItem((int) $companyId, $approvalItemId);
                    if ($approvalCartLock && (int) $approvalCartLock->user_id !== (int) $user->id) {
                        DB::rollBack();
                        $approvalSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                        return $this->saleCartLockedResponseForApproval($approvalItem, $approvalSet, $approvalCartLock);
                    }

                    $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                        $companyId,
                        $approvalItemId,
                        $resolvedApprovalCustomerId > 0 ? $resolvedApprovalCustomerId : null
                    )->load(['approval', 'itemSet', 'legacyItemSet']);
                } elseif ($rowApprovalCustomerId > 0 && $itemsetId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        null,
                        $itemsetId,
                        $rowApprovalCustomerId,
                        false
                    );

                    if ($approvalItem) {
                        $approvalItemId = (int) $approvalItem->id;
                        $approvalCartLock = $this->activeSaleCartLockForApprovalItem((int) $companyId, $approvalItemId);
                        if ($approvalCartLock && (int) $approvalCartLock->user_id !== (int) $user->id) {
                            DB::rollBack();
                            $approvalSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                            return $this->saleCartLockedResponseForApproval($approvalItem, $approvalSet, $approvalCartLock);
                        }

                        $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                            $companyId,
                            $approvalItemId,
                            $rowApprovalCustomerId
                        )->load(['approval', 'itemSet', 'legacyItemSet']);
                    }
                }

                $itemSet = null;
                if ($itemsetId > 0) {
                    $existingLock = $this->activeSaleCartLockForItemSet((int) $companyId, (int) $itemsetId);
                    if ($existingLock && (int) $existingLock->user_id !== (int) $user->id) {
                        DB::rollBack();
                        $lockedItem = ItemSet::with('item')
                            ->where('company_id', $companyId)
                            ->find($itemsetId);

                        return $lockedItem
                            ? $this->saleCartLockedResponse($lockedItem, $existingLock)
                            : response()->json([
                                'success' => false,
                                'message' => 'This item is already selected by another user.',
                                'code' => 'SALE_CART_ITEM_LOCKED',
                            ], 409);
                    }

                    $itemSet = TransactionItemGuard::lockItemSetForUse($companyId, $itemsetId, $approvalItemId > 0);
                    if (!$itemSet && $approvalItemId <= 0 && $itemsetId > 0) {
                        $approvalItem = $this->resolveApprovalItemForSale($companyId, null, $itemsetId, null, false);
                        if ($approvalItem) {
                            $approvalItemId = (int) $approvalItem->id;
                            $itemSet = ItemSet::where('company_id', $companyId)
                                ->where('id', $itemsetId)
                                ->first();
                        }
                    }
                    if (!$itemSet) {
                        return response()->json([
                            'success' => false,
                            'message' => 'ItemSet not found or not available for sale: ' . ($item['itemset_id'] ?? ''),
                        ], 422);
                    }
                } else {
                    $directItem = Item::where('company_id', $companyId)->find($productId);
                    if (!$directItem) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Item not found: ' . $productId,
                        ], 422);
                    }
                }

                $qty = max(1, (int) ($item['qty'] ?? 1));
                if ($approvalItemId > 0 && $approvalItem) {
                    $availableApprovalQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
                    if ($qty > $availableApprovalQty) {
                        throw new \RuntimeException("Only {$availableApprovalQty} piece(s) remain in this approval item.");
                    }
                }
                $batchQty = max(1, (int) ($itemSet?->quantity ?? 1));
                $unitValues = is_array($item['unit_values'] ?? null) ? $item['unit_values'] : [];
                $approvalQty = max(1, (int) ($approvalItem?->qty ?? 1));
                $grossWeight = (float) ($unitValues['unit_gross_weight'] ?? $item['gross_weight'] ?? $item['gross_wt'] ?? ($approvalItem ? ((float) $approvalItem->gross_weight / $approvalQty) : ((float) ($itemSet?->gross_weight ?? 0) / $batchQty)));
                $otherWeight = (float) ($unitValues['unit_other_weight'] ?? $item['other_weight'] ?? $item['other_wt'] ?? ($approvalItem ? ((float) $approvalItem->other_weight / $approvalQty) : ((float) ($itemSet?->other ?? 0) / $batchQty)));
                $netWeight = (float) ($unitValues['unit_net_weight'] ?? $item['net_weight'] ?? $item['net_wt'] ?? ($approvalItem ? ((float) ($approvalItem->net_weight ?? max(0, $approvalItem->gross_weight - $approvalItem->other_weight)) / $approvalQty) : ((float) ($itemSet?->net_weight ?? max(0, $grossWeight - $otherWeight)) / (!isset($item['net_weight'], $item['net_wt']) ? $batchQty : 1))));
                $purity = (float) ($item['purity'] ?? optional($itemSet?->item)->outward_purity ?? 0);
                $wastePercent = (float) ($item['waste_percent'] ?? $item['waste_pct'] ?? 0);
                $netPurity = $this->positiveOr($item['net_purity'] ?? null, $purity + $wastePercent);
                $fineWeight = $this->positiveOr($unitValues['unit_fine_weight'] ?? $item['fine_weight'] ?? $item['fine_wt'] ?? null, $approvalItem ? ((float) ($approvalItem->total_fine_weight ?? $approvalItem->fine_weight ?? ($netWeight * $netPurity / 100 * $approvalQty)) / $approvalQty) : ($netWeight * $netPurity / 100));
                $metalRate = (float) ($item['metal_rate'] ?? 0);
                $applyMetal = $this->truthy($item['apply_metal'] ?? null, true);
                $metalAmount = $applyMetal ? $this->positiveOr($unitValues['unit_metal_amount'] ?? $item['metal_amount'] ?? $item['metal_amt'] ?? null, $fineWeight * $metalRate) : 0;
                $labourRate = (float) ($item['labour_rate'] ?? $itemSet?->sale_labour_rate ?? 0);
                $applyLabour = $this->truthy($item['apply_labour'] ?? null, true);
                $labourAmount = $applyLabour ? $this->positiveOr($unitValues['unit_labour_amount'] ?? $item['labour_amount'] ?? $item['labour_amt'] ?? $item['sale_labour_amount'] ?? null, ((float) ($itemSet?->sale_labour_amount ?? ($netWeight * $labourRate))) / $batchQty) : 0;
                $otherAmount = (float) ($unitValues['unit_other_amount'] ?? $item['other_amount'] ?? $item['other_amt'] ?? ($approvalItem ? ((float) $approvalItem->other_amount / $approvalQty) : ((float) ($itemSet?->sale_other ?? 0) / $batchQty)));
                $lineTotal = $this->positiveOr($unitValues['unit_total_amount'] ?? $item['amount'] ?? $item['total_amount'] ?? $item['total_amt'] ?? null, $metalAmount + $labourAmount + $otherAmount) * $qty;
                $salePrice = $approvalItem
                    ? \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem)
                    : (float) ($unitValues['unit_sale_price'] ?? $item['sale_price'] ?? $itemSet?->billing_sale_price ?? 0);
                if (!$approvalItem && $itemSet && (float) $itemSet->wholesale_price > 0 && \App\Services\WholesalePrice::enabled($itemSet->pricingCompany)) {
                    $salePrice = (float) $itemSet->billing_sale_price;
                }
                if ($salePrice > 0) {
                    $metalAmount = $labourAmount = $otherAmount = $fineWeight = 0;
                    $lineTotal = $salePrice * $qty;
                }

                if ($itemSet && $approvalItemId <= 0) {
                    $availableQty = max(0, (int) ($itemSet->available_quantity ?? ($itemSet->is_sold ? 0 : 1)));
                    if ($qty > $availableQty) {
                        throw new \RuntimeException("Only {$availableQty} piece(s) remain for QR label {$itemSet->qr_code}.");
                    }
                }

                SaleItem::create([
                    ...\App\Services\FixedSalePrice::saleRequestPrice(array_merge($item, ['sale_price' => $salePrice ?: null])),
                    'sale_id' => $sale->id,
                    'itemset_id' => $itemSet?->id,
                    'product_id' => $item['product_id'] ?? $productId ?? $itemSet->item_id ?? null,
                    'approval_item_id' => $approvalItemId > 0 ? $approvalItemId : null,
                    'qty' => $qty,
                    'gross_weight' => $grossWeight * $qty,
                    'other_weight' => $otherWeight * $qty,
                    'net_weight' => $netWeight * $qty,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'fine_weight' => $fineWeight * $qty,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount * $qty,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount * $qty,
                    'other_amount' => $otherAmount * $qty,
                    'other_charge_details' => $this->normalizeOtherChargeDetails($item['other_charge_details'] ?? $item['other_charges'] ?? null),
                    'total_amount' => $lineTotal,
                    'remarks' => $item['remarks'] ?? $item['remark'] ?? null,
                ]);

                if ($approvalItemId > 0) {
                    $resolvedApprovalCustomerId = $rowApprovalCustomerId > 0
                        ? $rowApprovalCustomerId
                        : (int) optional($approvalItem?->approval)->customer_id;
                    $approvalItem = $approvalItem ?: TransactionItemGuard::lockApprovalItemForSale(
                        $companyId,
                        (int) $approvalItemId,
                        $resolvedApprovalCustomerId > 0 ? $resolvedApprovalCustomerId : null
                    );
                    if ($approvalItem) {
                        \App\Services\ApprovalItemAvailability::syncStatus($approvalItem->fresh());
                        $approvalIds[] = $approvalItem->approval_id;
                    }
                }

                if ($itemSet) {
                    if (empty($approvalItemId)) {
                        \App\Services\BatchInventory::consume($itemSet, $qty);
                    }
                    $soldItemsetIds[] = (int) $itemSet->id;
                }
                $total += $lineTotal;
            }

            $sale->update(['net_total' => $this->saleCashPayableTotal($sale->fresh('saleItems.itemset.item', 'saleItems.product'))]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale,
                (bool) $sale->use_silver_balance,
                (int) $user->id
            );
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
                'data' => $this->saleResponsePayload($sale->fresh(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments']))
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], str_contains($e->getMessage(), 'remain in this approval item') ? 422 : 500);
        }
    }

    public function show(Request $request, $id)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

        $sale = Sale::with('customer', 'saleItems.itemset.item', 'creator', 'payments')
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
            'data' => $this->saleResponsePayload($sale)
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $approvalCustomerId = (int) (
            $request->input('approval_customer_id')
            ?? $request->input('approval_person_id')
            ?? $request->input('approval_party_id')
            ?? 0
        );

        DB::beginTransaction();
       
        try {
            $sale = Sale::with('saleItems', 'payments')
                ->where('company_id', $companyId)
                ->findOrFail((int) $id);
            if (\App\Models\SaleReturnItem::whereIn('sale_item_id', $sale->saleItems->pluck('id'))->exists()) {
                throw new \Exception('This sale has returned items and cannot be rewritten.');
            }
                
            $request->validate([
                'customer_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'remarks' => 'nullable|string',
                'received_amount' => 'nullable|numeric|min:0',
                'additional_received_amount' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|max:30',
                'payment_reference' => 'nullable|string|max:120',
                'payment_note' => 'nullable|string|max:255',
            'show_previous_bills' => 'sometimes|boolean',
            ]);

            $customerExists = Customer::where('company_id', $companyId)
                ->where('id', (int) $request->customer_id)
                ->exists();
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid customer for this company.',
                ], 422);
            }

            $incomingReceived = (float) $request->input('received_amount', $sale->received_amount ?? 0);
            $additionalReceived = (float) $request->input('additional_received_amount', 0);
            $finalReceived = $additionalReceived > 0
                ? ((float) ($sale->received_amount ?? 0) + $additionalReceived)
                : $incomingReceived;
            $baseEffectiveReceived = $sale->appliedReceivedAmount();

            $sale->update([
                'customer_id' => (int) $request->customer_id,
                'remarks' => $request->input('remarks', $request->input('remark', $sale->remarks)),
                'received_amount' => $finalReceived,
                'payment_mode' => $request->input('payment_mode', $sale->payment_mode),
                'payment_reference' => $request->input('payment_reference', $sale->payment_reference),
                'payment_note' => $request->input('payment_note', $sale->payment_note),
                'show_previous_bills' => $request->has('show_previous_bills')
                    ? $request->boolean('show_previous_bills')
                    : (bool) $sale->show_previous_bills,
                'use_silver_balance' => $request->has('use_silver_balance')
                    ? $request->boolean('use_silver_balance')
                    : (bool) ($sale->use_silver_balance ?? false),
            ]);

            if ($additionalReceived > 0) {
                if ((float) ($sale->payments()->sum('amount')) <= 0 && $baseEffectiveReceived > 0) {
                    SalePayment::create([
                        'company_id' => $companyId,
                        'sale_id' => $sale->id,
                        'amount' => $baseEffectiveReceived,
                        'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                        'payment_mode' => $sale->payment_mode,
                        'payment_reference' => $sale->payment_reference,
                        'payment_note' => $sale->payment_note,
                        'created_by' => $user->id,
                    ]);
                }

                SalePayment::create([
                    'company_id' => $companyId,
                    'sale_id' => $sale->id,
                    'amount' => $additionalReceived,
                    'paid_on' => now()->toDateString(),
                    'payment_mode' => $request->input('payment_mode', $sale->payment_mode),
                    'payment_reference' => $request->input('payment_reference', $sale->payment_reference),
                    'payment_note' => $request->input('payment_note', $sale->payment_note),
                    'created_by' => $user->id,
                ]);
            } elseif ((float) ($sale->payments()->sum('amount')) <= 0 && $finalReceived > 0) {
                // backward compatibility for older data
                SalePayment::create([
                    'company_id' => $companyId,
                    'sale_id' => $sale->id,
                    'amount' => $finalReceived,
                    'paid_on' => Carbon::parse($sale->sale_date)->toDateString(),
                    'payment_mode' => $sale->payment_mode,
                    'payment_reference' => $sale->payment_reference,
                    'payment_note' => $sale->payment_note,
                    'created_by' => $user->id,
                ]);
            }

            $incomingRows = collect($request->input('items', []))
                ->filter(fn($row) => is_array($row))
                ->values();

            $existingSaleItemsetIds = $sale->saleItems
                ->pluck('itemset_id')
                ->filter()
                ->map(fn($itemsetId) => (int) $itemsetId)
                ->values()
                ->all();

            $resolvedRows = $incomingRows
                ->map(function ($row) use ($companyId) {
                    $resolved = $this->resolveSaleUpdateItem($row, $companyId);
                    if (!$resolved) {
                        return null;
                    }

                    return [
                        'row' => $row,
                        'itemset' => $resolved['itemset'],
                        'product' => $resolved['product'],
                    ];
                })
                ->filter()
                ->values();

            if ($resolvedRows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid item found. Use itemset_id/id, qr_code/code, huid/HUID, or item_id/product_id.',
                ], 422);
            }

            if ($resolvedRows->count() !== $incomingRows->count()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Some sale item rows are invalid or not found. Please send itemset_id, qr_code, HUID, or item_id for every row.',
                ], 422);
            }

            $duplicateItemsetIds = $resolvedRows
                ->map(fn($pair) => $pair['itemset'] ? (int) $pair['itemset']->id : null)
                ->filter()
                ->duplicates()
                ->unique()
                ->values()
                ->all();

            if (!empty($duplicateItemsetIds)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Duplicate scanned label(s) in request: ' . implode(', ', $duplicateItemsetIds),
                ], 422);
            }

            $approvalIds = [];

            $savedPrices = $sale->saleItems->pluck('sale_price', 'itemset_id');
            $retainedApprovalIds = array_map('intval', array_column(array_column($resolvedRows->all(), 'row'), 'approval_item_id'));
            foreach ($sale->saleItems as $saleItem) {
                if (!empty($saleItem->approval_item_id)) {
                    $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->find((int) $saleItem->approval_item_id);

                    if ($approvalItem) {
                        if (in_array((int) $approvalItem->id, $retainedApprovalIds, true)) {
                            $approvalItem->update(['status' => 'pending']);
                        } else {
                            app(\App\Services\SaleApprovalStockRestore::class)->restore($approvalItem, $saleItem, (int) $companyId);
                        }
                        $approvalIds[] = (int) $approvalItem->approval_id;
                    }
                }

                if (!empty($saleItem->itemset_id) && empty($saleItem->approval_item_id)) {
                    $restoredSet = ItemSet::where('company_id', $companyId)
                        ->where('id', (int) $saleItem->itemset_id)
                        ->lockForUpdate()
                        ->first();
                    if ($restoredSet) {
                        \App\Services\BatchInventory::restore($restoredSet, max(1, (int) ($saleItem->qty ?? 1)));
                    }
                }

                $saleItem->delete();
            }

            $total = 0;

            foreach ($resolvedRows as $pair) {
                $row = $pair['row'];
                $itemSet = $pair['itemset'];
                $product = $pair['product'];
                $itemsetId = $itemSet ? (int) $itemSet->id : null;

                $approvalItemId = (int) ($row['approval_item_id'] ?? 0);
                $rowApprovalCustomerId = (int) (
                    $row['approval_customer_id']
                    ?? $row['approval_person_id']
                    ?? $row['approval_party_id']
                    ?? $approvalCustomerId
                    ?? 0
                );
                $approvalItem = null;

                if ($approvalItemId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        $approvalItemId,
                        $itemsetId,
                        $rowApprovalCustomerId > 0 ? $rowApprovalCustomerId : null,
                        true
                    );

                    if (!$approvalItem) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approval item not found for selected approval person.',
                        ], 422);
                    }

                    $approvalItemId = (int) $approvalItem->id;
                    $resolvedApprovalCustomerId = $rowApprovalCustomerId > 0
                        ? $rowApprovalCustomerId
                        : (int) optional($approvalItem->approval)->customer_id;
                    $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                        $companyId,
                        $approvalItemId,
                        $resolvedApprovalCustomerId > 0 ? $resolvedApprovalCustomerId : null
                    )->load(['approval', 'itemSet', 'legacyItemSet']);
                } elseif ($rowApprovalCustomerId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        null,
                        $itemsetId,
                        $rowApprovalCustomerId,
                        false
                    );

                    if ($approvalItem) {
                        $approvalItemId = (int) $approvalItem->id;
                        $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                            $companyId,
                            $approvalItemId,
                            $rowApprovalCustomerId
                        )->load(['approval', 'itemSet', 'legacyItemSet']);
                    }
                } elseif ($itemSet && (int) $itemSet->is_sold === 1 && !in_array($itemsetId, $existingSaleItemsetIds, true)) {
                    $approvalItem = $this->resolveApprovalItemForSale($companyId, null, $itemsetId, null, false);
                    if ($approvalItem) {
                        $approvalItemId = (int) $approvalItem->id;
                        $approvalItem = TransactionItemGuard::lockApprovalItemForSale(
                            $companyId,
                            $approvalItemId
                        )->load(['approval', 'itemSet', 'legacyItemSet']);
                    }
                }

                if ($itemSet) {
                    $itemSet = TransactionItemGuard::lockItemSetForUse(
                        $companyId,
                        $itemsetId,
                        $approvalItemId > 0 || in_array($itemsetId, $existingSaleItemsetIds, true)
                    );
                }

                if ($itemSet && $approvalItemId <= 0 && (int) $itemSet->is_sold === 1 && !in_array($itemsetId, $existingSaleItemsetIds, true)) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ItemSet not available for sale: ' . $itemsetId,
                    ], 422);
                }

                $qty = max(1, (int) ($row['qty'] ?? 1));
                if ($approvalItemId > 0 && $approvalItem) {
                    $availableApprovalQty = \App\Services\ApprovalItemAvailability::availableQuantity($approvalItem);
                    if ($qty > $availableApprovalQty) {
                        throw new \RuntimeException("Only {$availableApprovalQty} piece(s) remain in this approval item.");
                    }
                }
                $batchQty = max(1, (int) ($itemSet?->quantity ?? 1));
                $grossWeight = (float) ($row['gross_weight'] ?? ((float) ($itemSet?->gross_weight ?? 0) / $batchQty));
                $otherWeight = (float) ($row['other_weight'] ?? ((float) ($itemSet?->other ?? 0) / $batchQty));
                $netWeight = (float) ($row['net_weight'] ?? max(0, $grossWeight - $otherWeight));
                $purity = (float) ($row['purity'] ?? $product?->outward_purity ?? 0);
                $wastePercent = (float) ($row['waste_percent'] ?? $row['waste'] ?? 0);
                $netPurity = $this->positiveOr($row['net_purity'] ?? null, $purity + $wastePercent);
                $fineWeight = $this->positiveOr($row['fine_weight'] ?? $row['fine_wt'] ?? null, $netWeight * $netPurity / 100);
                $metalRate = (float) ($row['metal_rate'] ?? 0);
                $applyMetal = $this->truthy($row['apply_metal'] ?? null, true);
                $metalAmount = $applyMetal ? $this->positiveOr($row['metal_amount'] ?? $row['metal_amt'] ?? null, $fineWeight * $metalRate) : 0;
                $labourRate = (float) ($row['labour_rate'] ?? $product?->labour_rate ?? 0);
                $applyLabour = $this->truthy($row['apply_labour'] ?? null, true);
                $labourAmount = $applyLabour ? $this->positiveOr($row['labour_amount'] ?? $row['labour_amt'] ?? null, $netWeight * $labourRate) : 0;
                $otherAmount = (float) ($row['other_amount'] ?? $row['other_amt'] ?? 0);
                $lineTotal = $this->positiveOr($row['total_amount'] ?? $row['total_amt'] ?? $row['amount'] ?? null, $metalAmount + $labourAmount + $otherAmount) * $qty;
                $salePrice = $approvalItem
                    ? \App\Services\ApprovalItemPricing::unitSalePrice($approvalItem)
                    : (float) ($row['sale_price'] ?? $itemSet?->billing_sale_price ?? 0);
                if ($salePrice > 0) {
                    $metalAmount = $labourAmount = $otherAmount = $fineWeight = 0;
                    $lineTotal = $salePrice * $qty;
                }
                if ($itemSet && empty($approvalItemId)) {
                    $availableQty = max(0, (int) ($itemSet->available_quantity ?? ($itemSet->is_sold ? 0 : 1)));
                    if ($qty > $availableQty) {
                        throw new \RuntimeException("Only {$availableQty} piece(s) remain for QR label {$itemSet->qr_code}.");
                    }
                }

                $payload = [
                    'sale_id' => $sale->id,
                    'itemset_id' => $itemsetId,
                    'product_id' => (int) ($row['product_id'] ?? $row['item_id'] ?? $product?->id ?? $itemSet?->item_id ?? 0),
                    'approval_item_id' => $approvalItemId > 0 ? $approvalItemId : null,
                    'qty' => $qty,
                    'sale_price' => $salePrice > 0 ? $salePrice : null,
                    'gross_weight' => $grossWeight * $qty,
                    'other_weight' => $otherWeight * $qty,
                    'net_weight' => $netWeight * $qty,
                    'purity' => $purity,
                    'waste_percent' => $wastePercent,
                    'net_purity' => $netPurity,
                    'fine_weight' => $fineWeight * $qty,
                    'metal_rate' => $metalRate,
                    'metal_amount' => $metalAmount * $qty,
                    'labour_rate' => $labourRate,
                    'labour_amount' => $labourAmount * $qty,
                    'other_amount' => $otherAmount * $qty,
                    'other_charge_details' => $this->normalizeOtherChargeDetails($row['other_charge_details'] ?? $row['other_charges'] ?? null),
                    'total_amount' => $lineTotal,
                    'remarks' => $row['remarks'] ?? $row['remark'] ?? null,
                ];

                if (isset($row['sale_price'])) {
                    $payload['sale_price'] = $row['sale_price'];
                } elseif (isset($savedPrices) && $savedPrices->has($itemsetId)) {
                    $payload['sale_price'] = $savedPrices->get($itemsetId);
                }
                SaleItem::create($payload);

                if ($itemSet) {
                    if (empty($approvalItemId)) {
                        \App\Services\BatchInventory::consume($itemSet, $qty);
                    }
                }

                if ($approvalItemId > 0) {
                    $resolvedApprovalCustomerId = $rowApprovalCustomerId > 0
                        ? $rowApprovalCustomerId
                        : (int) optional($approvalItem?->approval)->customer_id;
                    $approvalItem = $approvalItem ?: TransactionItemGuard::lockApprovalItemForSale(
                        $companyId,
                        (int) $approvalItemId,
                        $resolvedApprovalCustomerId > 0 ? $resolvedApprovalCustomerId : null
                    );
                    if ($approvalItem) {
                        \App\Services\ApprovalItemAvailability::syncStatus($approvalItem->fresh());
                        $approvalIds[] = (int) $approvalItem->approval_id;
                    }
                }

                $total += $lineTotal;
            }

            $existingUsage = $this->saleLedgerMetalUsage($companyId, (int) $sale->id);
            $advanceSummaryNow = $this->getAdvanceSummary($companyId, (int) $request->customer_id);
            $sale->update(['net_total' => $this->saleCashPayableTotal($sale->fresh('saleItems.itemset.item', 'saleItems.product'), [
                'gold' => (float) ($advanceSummaryNow['gold'] ?? 0) + (float) ($existingUsage['gold_used'] ?? 0),
                'silver' => (float) ($advanceSummaryNow['silver'] ?? 0) + (float) ($existingUsage['silver_used'] ?? 0),
                'other' => (float) ($advanceSummaryNow['other'] ?? 0) + (float) ($existingUsage['other_used'] ?? 0),
            ])]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale,
                (bool) $sale->use_silver_balance,
                (int) $user->id
            );
            $sale->increment('modified_count');
            $this->refreshApprovalHeaderStatus($approvalIds);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale updated successfully',
                'data' => $this->saleResponsePayload($sale->fresh(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], str_contains($e->getMessage(), 'remain in this approval item') ? 422 : 500);
        }
    }

    public function destroy(Request $request, $id)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

        DB::beginTransaction();

        try {
            $sale = Sale::with('saleItems')
                ->where('company_id', $companyId)
                ->findOrFail((int) $id);

            $hasReturns = DB::table('sale_returns')
                ->where('company_id', $companyId)
                ->where('sale_id', (int) $sale->id)
                ->exists();

            if ($hasReturns || \App\Models\SaleReturnItem::whereIn('sale_item_id', $sale->saleItems->pluck('id'))->exists()) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'This sale has return entries, so it cannot be deleted.',
                ], 422);
            }

            $restoreSummary = $this->restoreSaleItemsToSource($companyId, $sale->saleItems);

            SalePayment::where('company_id', $companyId)
                ->where('sale_id', (int) $sale->id)
                ->delete();
            CustomerAdvanceLedger::where('company_id', $companyId)
                ->where('reference_type', 'sale')
                ->where('reference_id', (int) $sale->id)
                ->delete();
            SaleItem::where('sale_id', (int) $sale->id)->delete();
            $sale->delete();

            $this->refreshApprovalHeaderStatus($restoreSummary['approval_ids']);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale deleted successfully. Items moved back to their original position.',
                'data' => [
                    'deleted_sale_id' => (int) $id,
                    'approval_items_restored' => $restoreSummary['approval_items'],
                    'stock_items_restored' => $restoreSummary['stock_items'],
                ],
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function removeSaleItem(Request $request, $saleId, $itemIdentifier)
    {
        $user = $request->user();
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId(request());
        $saleId = (int) $saleId;
        $itemIdentifier = (int) $itemIdentifier;

        DB::beginTransaction();

        try {
            $sale = Sale::where('company_id', $companyId)->findOrFail($saleId);

            $saleItem = SaleItem::where('sale_id', $sale->id)
                ->where('id', $itemIdentifier)
                ->first();

            if (!$saleItem) {
                $saleItem = SaleItem::where('sale_id', $sale->id)
                    ->where(function ($q) use ($itemIdentifier) {
                        $q->where('itemset_id', $itemIdentifier)
                            ->orWhere('approval_item_id', $itemIdentifier);
                    })
                    ->first();
            }

            if (!$saleItem) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Sale item not found for this sale.',
                ], 404);
            }

            $approvalIds = [];
            $removedSource = 'stock';

            if (!empty($saleItem->approval_item_id)) {
                $removedSource = 'approval';
                $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })->find((int) $saleItem->approval_item_id);

                if ($approvalItem) {
                    app(\App\Services\SaleApprovalStockRestore::class)->restore($approvalItem, $saleItem, (int) $companyId);
                    $approvalIds[] = (int) $approvalItem->approval_id;
                }

            } elseif (!empty($saleItem->itemset_id)) {
                $itemSet = ItemSet::where('company_id', $companyId)
                    ->where('id', (int) $saleItem->itemset_id)
                    ->lockForUpdate()
                    ->first();
                if ($itemSet) {
                    \App\Services\BatchInventory::restore($itemSet, max(1, (int) ($saleItem->qty ?? 1)));
                }
            }

            $saleItem->delete();
            $this->refreshApprovalHeaderStatus($approvalIds);

            $existingUsage = $this->saleLedgerMetalUsage($companyId, $sale->id);
            $advanceSummaryNow = $this->getAdvanceSummary($companyId, (int) $sale->customer_id);
            $freshSale = $sale->fresh('saleItems.itemset.item', 'saleItems.product');
            $sale->update(['net_total' => $this->saleCashPayableTotal($freshSale, [
                'gold' => (float) ($advanceSummaryNow['gold'] ?? 0) + (float) ($existingUsage['gold_used'] ?? 0),
                'silver' => (float) ($advanceSummaryNow['silver'] ?? 0) + (float) ($existingUsage['silver_used'] ?? 0),
                'other' => (float) ($advanceSummaryNow['other'] ?? 0) + (float) ($existingUsage['other_used'] ?? 0),
            ])]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale->fresh('saleItems.itemset.item', 'saleItems.product'),
                (bool) $sale->use_silver_balance,
                (int) $user->id
            );
            $sale->increment('modified_count');

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $removedSource === 'approval'
                    ? 'Sale approval item removed and returned to available shop stock.'
                    : 'Sale item removed and moved back to stock.',
                'removed_source' => $removedSource,
                'data' => $this->saleResponsePayload($sale->fresh(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments'])),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private function restoreSaleItemsToSource(int $companyId, $saleItems): array
    {
        $approvalIds = [];
        $summary = [
            'approval_ids' => [],
            'approval_items' => 0,
            'stock_items' => 0,
        ];

        foreach ($saleItems as $saleItem) {
            if (!empty($saleItem->approval_item_id)) {
                $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                    $q->where('company_id', $companyId);
                })->find((int) $saleItem->approval_item_id);

                if ($approvalItem) {
                    $approvalItem->update(['status' => 'pending']);
                    $approvalIds[] = (int) $approvalItem->approval_id;
                }

                if (!empty($saleItem->itemset_id)) {
                    ItemSet::where('company_id', $companyId)
                        ->where('id', (int) $saleItem->itemset_id)
                        ->update(['is_sold' => 1]);
                }

                $summary['approval_items']++;
                continue;
            }

            if (!empty($saleItem->itemset_id)) {
                ItemSet::where('company_id', $companyId)
                    ->where('id', (int) $saleItem->itemset_id)
                    ->update(['is_sold' => 0]);
                $summary['stock_items']++;
            }
        }

        $summary['approval_ids'] = array_values(array_unique($approvalIds));

        return $summary;
    }

    private function resolveApprovalItemForSale(
        int $companyId,
        ?int $approvalItemId = null,
        ?int $itemsetId = null,
        ?int $approvalCustomerId = null,
        bool $allowSold = false
    ): ?ApprovalItem {
        if (($approvalItemId ?? 0) <= 0 && ($itemsetId ?? 0) <= 0) {
            return null;
        }

        $query = ApprovalItem::with(['approval', 'itemSet', 'legacyItemSet'])
            ->whereHas('approval', function ($q) use ($companyId, $approvalCustomerId) {
                $q->where('company_id', $companyId);

                if (($approvalCustomerId ?? 0) > 0) {
                    $q->where('customer_id', $approvalCustomerId);
                }
            });

        if (!$allowSold) {
            $query->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) in (?, ?, ?)', ['pending', 'sold', 'returned']);
                });
        }

        if (($approvalItemId ?? 0) > 0) {
            $query->where('id', $approvalItemId);
        } else {
            $query->where(function ($q) use ($itemsetId) {
                $q->where('itemset_id', $itemsetId)
                    ->orWhereHas('itemSet', function ($sq) use ($itemsetId) {
                        $sq->where('id', $itemsetId);
                    })
                    ->orWhereHas('legacyItemSet', function ($sq) use ($itemsetId) {
                        $sq->where('id', $itemsetId);
                    });
            });
        }

        $item = $query->first();
        if ($item && \App\Services\ApprovalItemAvailability::availableQuantity($item) <= 0) {
            return null;
        }

        return $item;
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
            $base->whereDate('entry_date', '<=', Carbon::parse($asOnDate)->toDateString());
        }

        $cash = (clone $base)
            ->selectRaw('COALESCE(SUM(cash_in),0) - COALESCE(SUM(cash_out),0) as bal')
            ->value('bal');

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

    private function saleCashPayableTotal(Sale $sale, ?array $availableMetalOverride = null): float
    {
        $items = $sale->relationLoaded('saleItems')
            ? $sale->saleItems
            : $sale->saleItems()->with(['itemset.item', 'product'])->get();

        $useMetalBalance = (bool) ($sale->use_silver_balance ?? false);
        $availableMetal = $useMetalBalance
            ? ($availableMetalOverride ?: $this->getAdvanceSummary(
                (int) $sale->company_id,
                (int) ($sale->customer_id ?? 0),
                $sale->sale_date
            ))
            : ['gold' => 0, 'silver' => 0, 'other' => 0];

        $remainingMetal = [
            'gold' => max(0, (float) ($availableMetal['gold'] ?? 0)),
            'silver' => max(0, (float) ($availableMetal['silver'] ?? 0)),
            'other' => max(0, (float) ($availableMetal['other'] ?? 0)),
        ];
        $cashTotal = 0.0;

        foreach ($items as $row) {
            if ((float) $row->sale_price > 0) {
                $cashTotal += \App\Services\FixedSalePrice::amount($row->sale_price, (int) $row->qty);
                continue;
            }
            $cashTotal += (float) ($row->labour_amount ?? 0) + (float) ($row->other_amount ?? 0);

            $fine = max(0, (float) ($row->fine_weight ?? 0));
            $metalAmount = max(0, (float) ($row->metal_amount ?? 0));
            if ($fine <= 0 || $metalAmount <= 0) {
                continue;
            }

            $item = optional($row->itemset)->item ?: $row->product;
            $metalType = $this->normalizeMetalType(optional($item)->metal ?? null);
            $coveredFine = min($fine, (float) ($remainingMetal[$metalType] ?? 0));
            $remainingMetal[$metalType] = max(0, (float) ($remainingMetal[$metalType] ?? 0) - $coveredFine);
            $uncoveredFine = max(0, $fine - $coveredFine);

            if ($uncoveredFine > 0) {
                $cashTotal += $metalAmount * ($uncoveredFine / $fine);
            }
        }

        return round($cashTotal, 2);
    }

    private function saleLedgerMetalUsage(int $companyId, int $saleId): array
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

        return [
            'cash_used' => (float) ($usage->cash_used ?? 0),
            'gold_used' => (float) ($usage->gold_used ?? 0),
            'silver_used' => (float) ($usage->silver_used ?? 0),
            'other_used' => (float) ($usage->other_used ?? 0),
        ];
    }

    private function getSaleAdvanceUsage(int $companyId, Sale $sale): array
    {
        $usage = $this->saleLedgerMetalUsage($companyId, (int) $sale->id);
        $summary = $this->getAdvanceSummary($companyId, (int) ($sale->customer_id ?? 0));
        $required = ['gold' => 0.0, 'silver' => 0.0, 'other' => 0.0];

        $items = $sale->relationLoaded('saleItems')
            ? $sale->saleItems
            : $sale->saleItems()->with(['itemset.item', 'product'])->get();

        foreach ($items as $row) {
            $item = optional($row->itemset)->item ?: $row->product;
            $metalType = $this->normalizeMetalType(optional($item)->metal ?? null);
            $required[$metalType] += max(0, (float) ($row->fine_weight ?? 0));
        }

        $silverUsed = (float) ($usage['silver_used'] ?? 0);
        $silverClosing = (float) ($summary['silver'] ?? 0);
        $silverOpening = $silverClosing + $silverUsed;
        $silverRequired = (float) ($required['silver'] ?? 0);

        return [
            'cash_used' => (float) ($usage['cash_used'] ?? 0),
            'fine_required' => [
                'gold' => round((float) ($required['gold'] ?? 0), 3),
                'silver' => round($silverRequired, 3),
                'other' => round((float) ($required['other'] ?? 0), 3),
            ],
            'metal_used' => [
                'gold' => round((float) ($usage['gold_used'] ?? 0), 3),
                'silver' => round($silverUsed, 3),
                'other' => round((float) ($usage['other_used'] ?? 0), 3),
            ],
            'silver_opening' => round($silverOpening, 3),
            'silver_used' => round($silverUsed, 3),
            'silver_closing' => round($silverClosing, 3),
            'silver_debit' => round(max(0, -$silverClosing), 3),
            'silver_credit' => round(max(0, $silverClosing), 3),
            'cash_balance' => round((float) ($summary['cash'] ?? 0), 2),
            'cash_payable_total' => round((float) ($sale->net_total ?? 0), 2),
            'cash_pending' => round(max(0, (float) ($sale->net_total ?? 0) - ($sale->appliedReceivedAmount())), 2),
        ];
    }

    private function saleResponsePayload(Sale $sale): Sale
    {
        $sale->loadMissing(['customer', 'saleItems.itemset.item', 'saleItems.product', 'creator', 'payments']);

        $sale->setAttribute('can_edit_today', true);
        $sale->setAttribute('can_edit', true);
        $sale->setAttribute('created_by', optional($sale->creator)->name);
        $sale->setAttribute('modified_at', optional($sale->updated_at)?->format('Y-m-d H:i:s'));
        $sale->setAttribute('modified_count', (int) ($sale->modified_count ?? 0));
        $sale->setAttribute('qty_pcs', (int) $sale->saleItems->sum('qty'));
        $sale->setAttribute('gross_weight', (float) $sale->saleItems->sum('gross_weight'));
        $sale->setAttribute('net_weight', (float) $sale->saleItems->sum('net_weight'));
        $sale->setAttribute('fine_weight', (float) $sale->saleItems->sum('fine_weight'));
        $sale->setAttribute('metal_amount', (float) $sale->saleItems->sum('metal_amount'));
        $sale->setAttribute('labour_amount', (float) $sale->saleItems->sum('labour_amount'));
        $sale->setAttribute('other_amount', (float) $sale->saleItems->sum('other_amount'));
        $sale->setAttribute('refund_paid_amount', (float) ($sale->paid_amount ?? 0));
        $sale->setAttribute('pending_amount', max(0, (float) ($sale->net_total ?? 0) - ($sale->appliedReceivedAmount())));
        $sale->setAttribute('advance_usage', $this->getSaleAdvanceUsage((int) $sale->company_id, $sale));
        $sale->setAttribute('payment_history', collect($sale->payments ?? [])->map(function ($p) {
            return [
                'id' => (int) $p->id,
                'paid_on' => optional($p->paid_on)?->format('Y-m-d'),
                'amount' => (float) ($p->amount ?? 0),
                'payment_mode' => $p->payment_mode,
                'payment_reference' => $p->payment_reference,
                'payment_note' => $p->payment_note,
            ];
        })->values());

        $sale->saleItems->transform(function ($row) {
            $item = optional($row->itemset)->item ?: $row->product;
            $row->setAttribute('item_name', optional($item)->item_name);
            $row->setAttribute('metal_type', $this->normalizeMetalType(optional($item)->metal ?? null));
            $row->setAttribute('qty_pcs', max(1, (int) ($row->qty ?? 1)));
            $row->setAttribute('other_charges', $this->decodeOtherChargeDetails($row->other_charge_details ?? null));
            return $row;
        });

        return $sale;
    }

    private function formatAdvanceBalance(float $balance, int $decimals, string $labelPrefix): array
    {
        $type = $balance >= 0 ? 'Credit' : 'Debit';

        return [
            'balance' => $balance,
            'display_balance' => round(abs($balance), $decimals),
            'type' => $type,
            'label' => $labelPrefix . ' ' . $type,
        ];
    }

    private function resolveSaleUpdateItemSet(array $row, int $companyId): ?ItemSet
    {
        $query = ItemSet::where('company_id', $companyId)
            ->where('is_final', 1);

        $itemSetId = (int) ($row['itemset_id'] ?? $row['id'] ?? 0);
        if ($itemSetId > 0) {
            return (clone $query)->where('id', $itemSetId)->first();
        }

        $qrCode = trim((string) ($row['qr_code'] ?? $row['code'] ?? $row['barcode'] ?? $row['label_code'] ?? ''));
        if ($qrCode !== '') {
            return (clone $query)
                ->where(function ($q) use ($qrCode) {
                    $q->where('qr_code', $qrCode)
                        ->orWhere('barcode', $qrCode);
                })
                ->first();
        }

        $huid = trim((string) ($row['huid'] ?? $row['HUID'] ?? ''));
        if ($huid !== '') {
            return (clone $query)->where('HUID', $huid)->first();
        }

        return null;
    }

    private function resolveSaleUpdateItem(array $row, int $companyId): ?array
    {
        $itemSet = $this->resolveSaleUpdateItemSet($row, $companyId);
        if ($itemSet) {
            return [
                'itemset' => $itemSet,
                'product' => $itemSet->relationLoaded('item') ? $itemSet->item : $itemSet->item()->first(),
            ];
        }

        $itemId = (int) ($row['item_id'] ?? $row['product_id'] ?? 0);
        if ($itemId > 0) {
            $item = Item::where('company_id', $companyId)->find($itemId);
            if ($item) {
                return [
                    'itemset' => null,
                    'product' => $item,
                ];
            }
        }

        $code = trim((string) ($row['item_code'] ?? $row['code'] ?? ''));
        if ($code !== '') {
            $item = Item::where('company_id', $companyId)
                ->where('item_code', $code)
                ->first();
            if ($item) {
                return [
                    'itemset' => null,
                    'product' => $item,
                ];
            }
        }

        $name = trim((string) ($row['item_name'] ?? $row['name'] ?? ''));
        if ($name !== '') {
            $item = Item::where('company_id', $companyId)
                ->where('item_name', $name)
                ->first();
            if ($item) {
                return [
                    'itemset' => null,
                    'product' => $item,
                ];
            }
        }

        return null;
    }

    private function syncSilverAdvanceUsageForSale(int $companyId, Sale $sale, bool $useSilverBalance = false, ?int $userId = null): void
    {
        app(\App\Services\SaleSettlement::class)->sync($sale, $userId);
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

    private function previousPendingSalesForVoucher(Sale $sale)
    {
        if (empty($sale->company_id) || empty($sale->customer_id) || empty($sale->sale_date)) {
            return collect();
        }

        $saleDate = Carbon::parse($sale->sale_date)->toDateString();

        return Sale::query()
            ->withSum('saleItems as sum_qty', 'qty')
            ->withSum('saleItems as sum_fine_weight', 'fine_weight')
            ->withSum('saleItems as sum_metal_amount', 'metal_amount')
            ->where('company_id', (int) $sale->company_id)
            ->where('customer_id', (int) $sale->customer_id)
            ->where('id', '<>', (int) $sale->id)
            ->where(function ($query) use ($sale, $saleDate) {
                $query->whereDate('sale_date', '<', $saleDate)
                    ->orWhere(function ($sameDate) use ($sale, $saleDate) {
                        $sameDate->whereDate('sale_date', $saleDate)
                            ->where('id', '<', (int) $sale->id);
                    });
            })
            ->orderBy('sale_date')
            ->orderBy('id')
            ->get(['id', 'voucher_no', 'sale_date', 'net_total', 'received_amount', 'paid_amount', 'use_silver_balance'])
            ->map(function (Sale $row) {
                $received = (float) ($row->received_amount ?? 0);
                $refundPaid = (float) ($row->paid_amount ?? 0);
                $pending = max(0, (float) ($row->net_total ?? 0) - $row->appliedReceivedAmount());
                $fineWeight = (float) ($row->sum_fine_weight ?? 0);
                $metalAmount = (float) ($row->sum_metal_amount ?? 0);
                $metalDebit = (!(bool) ($row->use_silver_balance ?? false) && $metalAmount <= 0.000001) ? $fineWeight : 0;

                return [
                    'id' => (int) $row->id,
                    'voucher_no' => (string) ($row->voucher_no ?? '-'),
                    'sale_date' => $row->sale_date ? Carbon::parse($row->sale_date)->format('d-m-Y') : '-',
                    'qty' => (float) ($row->sum_qty ?? 0),
                    'total_amount' => (float) ($row->net_total ?? 0),
                    'received_amount' => $received,
                    'refund_paid' => $refundPaid,
                    'pending_amount' => $pending,
                    'fine_weight' => $fineWeight,
                    'metal_amount' => $metalAmount,
                    'fine_adjusted' => (bool) ($row->use_silver_balance ?? false),
                    'metal_debit' => $metalDebit,
                ];
            })
            ->filter(fn($row) => (float) $row['pending_amount'] > 0.000001)
            ->values();
    }

    public function pdf(Request $request, $id)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

        $company = Company::find($companyId);
        $sale = Sale::with(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments'])
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found'
            ], 404);
        }

        $advanceSummary = $this->getAdvanceSummary($companyId, (int) ($sale->customer_id ?? 0), $sale->sale_date);
        $saleAdvanceUsage = $this->getSaleAdvanceUsage($companyId, $sale);
        $cashPayableTotal = $this->saleCashPayableTotal($sale);
        $previousPendingSales = $this->previousPendingSalesForVoucher($sale);

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale', 'company', 'advanceSummary', 'saleAdvanceUsage', 'cashPayableTotal', 'previousPendingSales'))
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

        $company = Company::find($companyId);
        $sale = Sale::with(['customer', 'saleItems.itemset.item', 'saleItems.product', 'payments'])
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $advanceSummary = $this->getAdvanceSummary($companyId, (int) ($sale->customer_id ?? 0), $sale->sale_date);
        $saleAdvanceUsage = $this->getSaleAdvanceUsage($companyId, $sale);
        $cashPayableTotal = $this->saleCashPayableTotal($sale);
        $previousPendingSales = $this->previousPendingSalesForVoucher($sale);

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale', 'company', 'advanceSummary', 'saleAdvanceUsage', 'cashPayableTotal', 'previousPendingSales'))
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
            \App\Services\ApprovalItemAvailability::syncHeaderStatus((int) $approvalId);
        }
    }

    private function excludeSaleCartLocks($query, int $companyId, ?int $userId = null): void
    {
        $activeLockCutoff = now()->subMinutes(30);

        $query->whereNotExists(function ($sub) use ($companyId, $userId, $activeLockCutoff) {
            $sub->select(DB::raw(1))
                ->from('sale_carts')
                ->whereColumn('sale_carts.itemset_id', 'item_sets.id')
                ->where('sale_carts.company_id', $companyId)
                ->whereNotNull('sale_carts.itemset_id')
                ->where('sale_carts.created_at', '>=', $activeLockCutoff);

            if ($userId) {
                $sub->where('sale_carts.user_id', '<>', $userId);
            }
        });
    }

    private function excludeApprovalSaleCartLocks($query, int $companyId, ?int $userId = null): void
    {
        $activeLockCutoff = now()->subMinutes(30);

        $query->whereNotExists(function ($sub) use ($companyId, $userId, $activeLockCutoff) {
            $sub->select(DB::raw(1))
                ->from('sale_carts')
                ->whereColumn('sale_carts.approval_item_id', 'approval_items.id')
                ->where('sale_carts.company_id', $companyId)
                ->whereNotNull('sale_carts.approval_item_id')
                ->where('sale_carts.created_at', '>=', $activeLockCutoff);

            if ($userId) {
                $sub->where('sale_carts.user_id', '<>', $userId);
            }
        });
    }

    private function activeSaleCartLockForItemSet(int $companyId, int $itemsetId): ?SaleCart
    {
        if (!Schema::hasTable('sale_carts')) {
            return null;
        }

        return SaleCart::with('user')
            ->where('company_id', $companyId)
            ->where('itemset_id', $itemsetId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();
    }

    private function activeSaleCartLockForApprovalItem(int $companyId, int $approvalItemId): ?SaleCart
    {
        if (!Schema::hasTable('sale_carts') || !Schema::hasColumn('sale_carts', 'approval_item_id')) {
            return null;
        }

        return SaleCart::with('user')
            ->where('company_id', $companyId)
            ->where('approval_item_id', $approvalItemId)
            ->where('created_at', '>=', now()->subMinutes(30))
            ->latest('id')
            ->first();
    }

    private function saleCartLockedResponse(ItemSet $item, SaleCart $cart)
    {
        return response()->json([
            'success' => false,
            'message' => $this->saleCartLockMessage($item),
            'code' => 'SALE_CART_ITEM_LOCKED',
            'locked_item' => $this->saleCartLockPayload($item, $cart),
            'data' => [$this->saleCartLockPayload($item, $cart)],
        ], 409);
    }

    private function saleCartLockMessage(ItemSet $item): string
    {
        $label = trim((string) ($item->qr_code ?: $item->barcode ?: $item->HUID ?: optional($item->item)->item_name ?: 'This item'));

        return $label . ' is already selected by another user.';
    }

    private function saleCartLockPayload(ItemSet $item, SaleCart $cart): array
    {
        return [
            'cart_id' => (int) $cart->id,
            'itemset_id' => (int) $item->id,
            'item_id' => (int) ($item->item_id ?? 0),
            'item_name' => (string) (optional($item->item)->item_name ?? ''),
            'qr_code' => (string) ($item->qr_code ?? ''),
            'barcode' => (string) ($item->barcode ?? ''),
            'huid' => (string) ($item->HUID ?? ''),
            'locked_by_user_id' => (int) ($cart->user_id ?? 0),
            'locked_by' => (string) (optional($cart->user)->name ?? ''),
            'locked_at' => optional($cart->created_at)->toDateTimeString(),
        ];
    }

    private function saleCartLockedResponseForApproval(ApprovalItem $approvalItem, ?ItemSet $itemSet, SaleCart $cart)
    {
        $payload = $this->saleCartApprovalLockPayload($approvalItem, $itemSet, $cart);

        return response()->json([
            'success' => false,
            'message' => ($payload['qr_code'] ?: $payload['item_name'] ?: 'This approval item') . ' is already selected by another user.',
            'code' => 'SALE_CART_ITEM_LOCKED',
            'locked_item' => $payload,
            'data' => [$payload],
        ], 409);
    }

    private function saleCartApprovalLockPayload(ApprovalItem $approvalItem, ?ItemSet $itemSet, SaleCart $cart): array
    {
        $item = optional($itemSet)->item ?: $approvalItem->item;

        return [
            'cart_id' => (int) $cart->id,
            'source' => 'approval',
            'approval_item_id' => (int) $approvalItem->id,
            'approval_id' => (int) ($approvalItem->approval_id ?? 0),
            'itemset_id' => (int) (optional($itemSet)->id ?? 0),
            'item_id' => (int) ($approvalItem->item_id ?? optional($itemSet)->item_id ?? 0),
            'item_name' => (string) (optional($item)->item_name ?? ''),
            'qr_code' => (string) ($approvalItem->qr_code ?? optional($itemSet)->qr_code ?? ''),
            'barcode' => (string) (optional($itemSet)->barcode ?? ''),
            'huid' => (string) ($approvalItem->huid ?? optional($itemSet)->HUID ?? ''),
            'locked_by_user_id' => (int) ($cart->user_id ?? 0),
            'locked_by' => (string) (optional($cart->user)->name ?? ''),
            'locked_at' => optional($cart->created_at)->toDateTimeString(),
        ];
    }

    private function decodeOtherChargeDetails($value): array
    {
        if (is_array($value)) {
            return array_values($value);
        }

        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return [];
        }

        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function normalizeOtherChargeDetails($value): ?string
    {
        if (is_array($value)) {
            $value = array_values(array_filter($value, fn($row) => is_array($row) || trim((string) $row) !== ''));
            return empty($value) ? null : json_encode($value);
        }

        $value = trim((string) ($value ?? ''));
        return $value === '' ? null : $value;
    }

    private function positiveOr($value, float $fallback): float
    {
        $number = (float) ($value ?? 0);
        return $number > 0 ? $number : $fallback;
    }

    private function truthy($value, bool $default = true): bool
    {
        if ($value === null || $value === '') {
            return $default;
        }

        $parsed = filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        return $parsed ?? ((float) $value !== 0.0);
    }
}
