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

class SaleApiController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;
        $companyId = $company->id;

        $sales = Sale::with(['customer', 'creator', 'payments'])
            ->withSum('saleItems as total_qty', 'qty')
            ->withSum('saleItems as total_gross_weight', 'gross_weight')
            ->withSum('saleItems as total_net_weight', 'net_weight')
            ->withSum('saleItems as total_fine_weight', 'fine_weight')
            ->withSum('saleItems as total_metal_amount', 'metal_amount')
            ->withSum('saleItems as total_labour_amount', 'labour_amount')
            ->withSum('saleItems as total_other_amount', 'other_amount')
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        $data = $sales->map(function ($sale) use ($company) {
            $received = (float) ($sale->received_amount ?? 0);
            $refundPaid = (float) ($sale->paid_amount ?? 0);
            $effectiveReceived = $received - $refundPaid;
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
        $company = $request->user()->company;
        [$fromDate, $toDate] = $this->resolveSaleDateRange($request);

        $rows = $this->saleListQuery($company->id, $request, $fromDate, $toDate)
            ->get()
            ->map(fn(Sale $sale) => $this->summarizeSaleListRow($sale))
            ->values();

        $fileName = 'sales-list-' . now()->format('YmdHis') . '.pdf';
        $pdf = Pdf::loadView('company.sales.list_pdf', compact('company', 'rows', 'fromDate', 'toDate'))
            ->setPaper('a4', 'landscape');

        if ($request->boolean('download')) {
            return $pdf->download($fileName);
        }

        return $pdf->stream($fileName);
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

    public function customerlist(Request $request)
    {
        $companyId = $request->user()->company_id;

        $customers = Customer::where('company_id', $companyId)
            ->where('is_active', 1)
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
        $companyId = $request->user()->company_id;
        $search = trim((string) $request->input('search', ''));
        $customerId = (int) $request->input('customer_id', 0);
        $limit = max(10, min((int) $request->input('limit', 1000), 2000));

        $approvalItems = collect();
        if ($customerId > 0) {
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
                        ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
                })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_items')
                        ->where(function ($q) {
                            $q->whereColumn('sale_items.approval_item_id', 'approval_items.id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.itemset_id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.item_id');
                        });
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
                ->get();
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

        $itemSets = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->where('is_final', 1)
            ->where('is_sold', 0)
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
            $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0);
            $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
            $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity + $wastePercent));
            $fineWeight = (float) ($approvalItem->total_fine_weight ?? (($net * $netPurity) / 100));
            $metalRate = (float) ($approvalItem->metal_rate ?? 0);
            $metalAmount = (float) ($approvalItem->metal_amount ?? ($net * $metalRate));
            $labourRate = (float) ($approvalItem->labour_rate ?? optional($itemSet)->sale_labour_rate ?? optional($item)->labour_rate ?? 0);
            $labourAmount = (float) ($approvalItem->labour_amount ?? ($net * $labourRate));
            $otherAmount = (float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0);
            $totalAmount = (float) ($approvalItem->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));

            return [
                'id' => (int) (optional($itemSet)->id ?? 0),
                'itemset_id' => (int) (optional($itemSet)->id ?? 0),
                'approval_item_id' => (int) $approvalItem->id,
                'approval_id' => (int) ($approvalItem->approval_id ?? 0),
                'item_id' => (int) ($approvalItem->item_id ?? optional($itemSet)->item_id ?? 0),
                'name' => (string) (optional($item)->item_name ?? ''),
                'code' => (string) ($approvalItem->qr_code ?? optional($itemSet)->qr_code ?? ''),
                'huid' => (string) ($approvalItem->huid ?? optional($itemSet)->HUID ?? ''),
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
                'remarks' => (string) ($approvalItem->remarks ?? ''),
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
                'itemset_id' => (int) $set->id,
                'item_id' => (int) ($set->item_id ?? 0),
                'name' => (string) (optional($set->item)->item_name ?? ''),
                'code' => (string) ($set->qr_code ?? ''),
                'huid' => (string) ($set->HUID ?? ''),
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
                'remarks' => '',
                'is_item_only' => false,
                'source' => 'itemset',
            ];
        })->values();

        $itemOnlyRows = $itemOnly->map(function ($item) {
            return [
                'id' => 0,
                'itemset_id' => 0,
                'item_id' => (int) $item->id,
                'name' => (string) ($item->item_name ?? ''),
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
            'qr_code' => 'required|string',
            'customer_id' => 'nullable|integer',
        ]);

        $qrCode = trim((string) $request->qr_code);
        $customerId = (int) $request->input('customer_id', 0);
       
        if ($customerId > 0) {
            if (!Schema::hasColumn('sale_carts', 'approval_item_id')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Sale cart approval item column missing. Please run migration.'
                ], 500);
            }
            
            $approvalItem = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item', 'item'])
                ->whereExists(function ($q) use ($user, $customerId) {
                    $q->select(DB::raw(1))
                        ->from('approval_headers')
                        ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                        ->where('approval_headers.company_id', $user->company_id)
                        ->where('approval_headers.customer_id', $customerId);
                })
                ->where(function ($q) {
                    $q->whereNull('status')
                        ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
                })
                ->whereRaw('TRIM(qr_code) = ?', [$qrCode])
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_items')
                        ->where(function ($q) {
                            $q->whereColumn('sale_items.approval_item_id', 'approval_items.id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.itemset_id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.item_id');
                        });
                })
                ->latest('id')
                ->first();
                 
            if ($approvalItem) {
                $exists = SaleCart::where('user_id', $user->id)
                    ->where('company_id', $user->company_id)
                    ->where('approval_item_id', $approvalItem->id)
                    ->exists();

                if ($exists) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Product already added. Please add different product.'
                    ]);
                }

                $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                SaleCart::create([
                    'user_id' => $user->id,
                    'company_id' => $user->company_id,
                    'itemset_id' => optional($itemSet)->id,
                    'approval_item_id' => $approvalItem->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Approval product added to cart',
                    'source' => 'approval',
                    'approval_item' => $approvalItem,
                ]);
            }
        }

        $item = ItemSet::where('company_id', $user->company_id)
            ->where('qr_code', $qrCode)
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

        $items = SaleCart::with([
                'itemset.item',
                'approvalItem.approval.customer',
                'approvalItem.itemSet.item',
                'approvalItem.legacyItemSet.item',
                'approvalItem.item',
            ])
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->get()
            ->map(function ($cart) {
                if ($cart->approvalItem) {
                    $approvalItem = $cart->approvalItem;
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    $item = optional($itemSet)->item ?? $approvalItem->item;
                    $grossWeight = (float) (optional($itemSet)->gross_weight ?? $approvalItem->gross_weight ?? 0);
                    $otherWeight = (float) (optional($itemSet)->other ?? $approvalItem->other_weight ?? 0);
                    $netWeight = (float) (optional($itemSet)->net_weight ?? $approvalItem->net_weight ?? max(0, $grossWeight - $otherWeight));

                    return [
                        'id' => $cart->id,
                        'source' => 'approval',
                        'approval_item_id' => $approvalItem->id,
                        'approval_id' => $approvalItem->approval_id,
                        'itemset_id' => optional($itemSet)->id,
                        'item_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                        'qr_code' => $approvalItem->qr_code ?? optional($itemSet)->qr_code,
                        'huid' => $approvalItem->huid ?? optional($itemSet)->HUID,
                        'item_name' => optional($item)->item_name,
                        'gross_weight' => number_format($grossWeight, 3, '.', ''),
                        'other_weight' => number_format($otherWeight, 3, '.', ''),
                        'net_weight' => number_format($netWeight, 3, '.', ''),
                        'purity' => number_format((float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0), 3, '.', ''),
                        'waste_percent' => number_format((float) ($approvalItem->waste_percent ?? 0), 3, '.', ''),
                        'net_purity' => number_format((float) ($approvalItem->net_purity ?? $approvalItem->purity ?? optional($item)->outward_purity ?? 0), 3, '.', ''),
                        'fine_weight' => number_format((float) ($approvalItem->total_fine_weight ?? 0), 3, '.', ''),
                        'metal_rate' => number_format((float) ($approvalItem->metal_rate ?? 0), 2, '.', ''),
                        'metal_amount' => number_format((float) ($approvalItem->metal_amount ?? 0), 2, '.', ''),
                        'labour_rate' => number_format((float) ($approvalItem->labour_rate ?? 0), 2, '.', ''),
                        'labour_amount' => number_format((float) ($approvalItem->labour_amount ?? 0), 2, '.', ''),
                        'other_amount' => number_format((float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0), 2, '.', ''),
                        'total_amount' => number_format((float) ($approvalItem->total_amount ?? 0), 2, '.', ''),
                    ];
                }

                return $cart;
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
            ->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc(DB::raw('COALESCE(printed_at, created_at)'))
            ->orderByDesc('serial_no')
            ->orderByDesc('id')
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
        $labelFormat = $this->resolveLabelFormat((string) $request->input('label_format', 'compact'));
        $startPosition = $this->resolveStartPosition($request->input('start_position', 1));

        $idsParam = $request->input('ids', []);
        if (is_string($idsParam)) {
            $ids = array_values(array_filter(explode(',', $idsParam)));
        } elseif (is_array($idsParam)) {
            $ids = $idsParam;
        } else {
            $ids = [];
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn($id) => (string) $id !== ''))));

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
        $idOrder = array_flip($ids);
        $itemSets = $itemSets->sortBy(fn($set) => $idOrder[$set->id] ?? PHP_INT_MAX)->values();

        if ($itemSets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No labels found for provided ids.',
            ], 404);
        }

        ItemSet::where('company_id', $user->company_id)
            ->whereIn('id', $itemSets->pluck('id'))
            ->whereNull('printed_at')
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

        $printPages = $this->buildPrintPages($itemSets, $startPosition);
        $pdf = Pdf::loadView('api.item_sets.print_direct_pdf', compact('itemSets', 'labelFormat', 'startPosition', 'printPages'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->download('qr-codes.pdf');
    }

    public function directQrPdfForApp(Request $request)
    {
        $user = auth()->user();
        $labelFormat = $this->resolveLabelFormat((string) $request->input('label_format', 'compact'));
        $startPosition = $this->resolveStartPosition($request->input('start_position', 1));

        $idsParam = $request->input('ids', []);
        if (is_string($idsParam)) {
            $ids = array_values(array_filter(explode(',', $idsParam)));
        } elseif (is_array($idsParam)) {
            $ids = $idsParam;
        } else {
            $ids = [];
        }

        $ids = array_values(array_unique(array_map('intval', array_filter($ids, fn($id) => (string) $id !== ''))));

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
        $idOrder = array_flip($ids);
        $itemSets = $itemSets->sortBy(fn($set) => $idOrder[$set->id] ?? PHP_INT_MAX)->values();

        if ($itemSets->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No labels found for provided ids.',
            ], 404);
        }

        ItemSet::where('company_id', $user->company_id)
            ->whereIn('id', $itemSets->pluck('id'))
            ->whereNull('printed_at')
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

        $printPages = $this->buildPrintPages($itemSets, $startPosition);
        $pdf = Pdf::loadView('api.item_sets.print_direct_pdf', compact('itemSets', 'labelFormat', 'startPosition', 'printPages'));
        $pdf->setPaper('A4', 'portrait');

        return $pdf->stream('qr-codes-app.pdf');
    }

    private function resolveLabelFormat(string $labelFormat): string
    {
        $normalized = strtolower(trim($labelFormat));

        $map = [
            'compact' => 'compact',
            'default' => 'compact',
            'compact (default)' => 'compact',
            'double_barcode' => 'double_barcode',
            'double barcode' => 'double_barcode',
            'two barcode + name/gross' => 'double_barcode',
            'two barcode + name gross' => 'double_barcode',
            'double_details' => 'double_details',
            '50%-50% qr + details' => 'double_details',
            '50 50 qr + details' => 'double_details',
        ];

        if (!array_key_exists($normalized, $map)) {
            return 'compact';
        }

        return $map[$normalized];
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
            $companyId = (int) $user->company_id;

            $request->validate([
                'customer_id' => 'required|integer',
                'remarks' => 'nullable|string',
                'received_amount' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|max:30',
                'payment_reference' => 'nullable|string|max:120',
                'payment_note' => 'nullable|string|max:255',
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
                'remarks' => $request->input('remarks', $request->input('remark')),
                'net_total'   => 0,
                'received_amount' => (float) $request->input('received_amount', 0),
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
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
                    $approvalItem = ApprovalItem::with(['itemSet.item', 'legacyItemSet.item', 'item'])
                        ->whereExists(function ($q) use ($user, $request) {
                            $q->select(DB::raw(1))
                                ->from('approval_headers')
                                ->whereColumn('approval_headers.id', 'approval_items.approval_id')
                                ->where('approval_headers.company_id', $user->company_id)
                                ->where('approval_headers.customer_id', (int) $request->customer_id);
                        })
                        ->where(function ($q) {
                            $q->whereNull('status')
                                ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
                        })
                        ->find($cart->approval_item_id);

                    if (!$approvalItem) {
                        throw new \Exception('Approval item not available for sale');
                    }

                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    $item = optional($itemSet)->item ?? $approvalItem->item;
                    $gross = (float) (optional($itemSet)->gross_weight ?? $approvalItem->gross_weight ?? 0);
                    $otherWeight = (float) (optional($itemSet)->other ?? $approvalItem->other_weight ?? 0);
                    $net = (float) (optional($itemSet)->net_weight ?? $approvalItem->net_weight ?? max(0, $gross - $otherWeight));
                    $purity = (float) ($approvalItem->purity ?? optional($item)->outward_purity ?? 0);
                    $wastePercent = (float) ($approvalItem->waste_percent ?? 0);
                    $netPurity = (float) ($approvalItem->net_purity ?? max(0, $purity + $wastePercent));
                    $fineWeight = (float) ($approvalItem->total_fine_weight ?? (($net * $netPurity) / 100));
                    $metalAmount = (float) ($approvalItem->metal_amount ?? 0);
                    $labourAmount = (float) ($approvalItem->labour_amount ?? 0);
                    $otherAmount = (float) ($approvalItem->other_amount ?? optional($itemSet)->sale_other ?? 0);
                    $lineTotal = (float) ($approvalItem->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));

                    SaleItem::create([
                        'sale_id' => $sale->id,
                        'itemset_id' => optional($itemSet)->id,
                        'product_id' => $approvalItem->item_id ?? optional($itemSet)->item_id,
                        'qty' => 1,
                        'gross_weight' => $gross,
                        'other_weight' => $otherWeight,
                        'net_weight' => $net,
                        'purity' => $purity,
                        'waste_percent' => $wastePercent,
                        'net_purity' => $netPurity,
                        'fine_weight' => $fineWeight,
                        'metal_rate' => (float) ($approvalItem->metal_rate ?? 0),
                        'metal_amount' => $metalAmount,
                        'labour_rate' => (float) ($approvalItem->labour_rate ?? 0),
                        'labour_amount' => $labourAmount,
                        'other_amount' => $otherAmount,
                        'total_amount' => $lineTotal,
                        'approval_item_id' => $approvalItem->id,
                    ]);

                    $approvalItem->update(['status' => 'sold']);
                    if ($itemSet) {
                        $itemSet->update(['is_sold' => 1]);
                    }

                    $total += $lineTotal;
                    continue;
                }

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

        $approvalIds = ApprovalHeader::where('company_id', $companyId)
            ->where('customer_id', $customerId)
            ->whereIn('status', ['open', 'partial'])
            ->pluck('id');

        $rows = ApprovalItem::with(['approval.customer', 'itemSet.item', 'legacyItemSet.item'])
            ->whereIn('approval_id', $approvalIds)
            ->where(function ($q) {
                $q->whereNull('status')
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_items')
                    ->where(function ($q) {
                        $q->whereColumn('sale_items.approval_item_id', 'approval_items.id')
                            ->orWhereColumn('sale_items.itemset_id', 'approval_items.itemset_id')
                            ->orWhereColumn('sale_items.itemset_id', 'approval_items.item_id');
                    });
            })
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
                    'approval_customer_id' => optional($row->approval)->customer_id,
                    'approval_customer_name' => optional(optional($row->approval)->customer)->name,
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

        DB::beginTransaction();

        try {
            $sale = Sale::create([
                'company_id'  => $companyId,
                'customer_id' => $customerId,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'remarks' => $request->input('remarks', $request->input('remark')),
                'net_total'   => 0,
                'received_amount' => (float) $request->input('received_amount', 0),
                'payment_mode' => $request->input('payment_mode'),
                'payment_reference' => $request->input('payment_reference'),
                'payment_note' => $request->input('payment_note'),
                'use_silver_balance' => (bool) $request->input('use_silver_balance', true),
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
                        $approvalCustomerId > 0 ? $approvalCustomerId : null,
                        false
                    );

                    if (!$approvalItem) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approval item not found or already sold for selected approval person.',
                        ], 422);
                    }

                    $approvalItemId = (int) $approvalItem->id;
                } elseif ($approvalCustomerId > 0 && $itemsetId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        null,
                        $itemsetId,
                        $approvalCustomerId,
                        false
                    );

                    if ($approvalItem) {
                        $approvalItemId = (int) $approvalItem->id;
                    }
                }

                $itemSet = null;
                if ($itemsetId > 0) {
                    $itemSetQuery = ItemSet::where('company_id', $companyId)
                        ->where('id', $itemsetId);

                    // For normal sale/scanner/manual rows, only unsold labels are allowed.
                    // For approval-conversion rows, label can already be marked sold (outward on approval).
                    if ($approvalItemId <= 0) {
                        $itemSetQuery->where('is_sold', 0);
                    }

                    $itemSet = $itemSetQuery->first();
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

                $grossWeight = (float) ($item['gross_weight'] ?? $item['gross_wt'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($item['other_weight'] ?? $item['other_wt'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($item['net_weight'] ?? $item['net_wt'] ?? $itemSet->net_weight ?? max(0, $grossWeight - $otherWeight));
                $purity = (float) ($item['purity'] ?? optional($itemSet?->item)->outward_purity ?? 0);
                $wastePercent = (float) ($item['waste_percent'] ?? $item['waste_pct'] ?? 0);
                $netPurity = (float) ($item['net_purity'] ?? ($purity - $wastePercent));
                $fineWeight = (float) ($item['fine_weight'] ?? $item['fine_wt'] ?? ($netWeight * $netPurity / 100));
                $metalRate = (float) ($item['metal_rate'] ?? 0);
                $metalAmount = (float) ($item['metal_amount'] ?? $item['metal_amt'] ?? ($fineWeight * $metalRate));
                $labourRate = (float) ($item['labour_rate'] ?? $itemSet->sale_labour_rate ?? 0);
                $labourAmount = (float) ($item['labour_amount'] ?? $item['labour_amt'] ?? $itemSet->sale_labour_amount ?? ($netWeight * $labourRate));
                $otherAmount = (float) ($item['other_amount'] ?? $item['other_amt'] ?? $itemSet->sale_other ?? 0);
                $lineTotal = (float) ($item['amount'] ?? $item['total_amount'] ?? $item['total_amt'] ?? ($metalAmount + $labourAmount + $otherAmount));
                $qty = (int) ($item['qty'] ?? 1);

                SaleItem::create([
                    'sale_id' => $sale->id,
                    'itemset_id' => $itemSet?->id ?: 0,
                    'product_id' => $item['product_id'] ?? $productId ?? $itemSet->item_id ?? null,
                    'approval_item_id' => $approvalItemId > 0 ? $approvalItemId : null,
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

                if ($approvalItemId > 0) {
                    $approvalItem = $approvalItem ?: ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->find($approvalItemId);
                    if ($approvalItem) {
                        $approvalItem->update(['status' => 'sold']);
                        $approvalIds[] = $approvalItem->approval_id;
                    }
                }

                if ($itemSet) {
                    $itemSet->update(['is_sold' => 1]);
                    $soldItemsetIds[] = (int) $itemSet->id;
                }
                $total += $lineTotal;
            }

            $sale->update(['net_total' => $total]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale,
                (bool) ($sale->use_silver_balance ?? true),
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

        $sale = Sale::with('customer', 'saleItems.itemset.item', 'creator', 'payments')
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found'
            ]);
        }

        $sale->setAttribute(
            'can_edit_today',
            true
        );
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
        $received = (float) ($sale->received_amount ?? 0);
        $refundPaid = (float) ($sale->paid_amount ?? 0);
        $sale->setAttribute('refund_paid_amount', $refundPaid);
        $sale->setAttribute('pending_amount', max(0, (float) ($sale->net_total ?? 0) - ($received - $refundPaid)));
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

        // Add item_name in each sale item for app-side direct consumption.
        $sale->saleItems->transform(function ($row) {
            $itemName = optional(optional($row->itemset)->item)->item_name;
            $row->setAttribute('item_name', $itemName);
            return $row;
        });

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }

    public function update(Request $request, $id)
    {
        $user = $request->user();
        $companyId = $request->user()->company_id;
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
                
            $request->validate([
                'customer_id' => 'required|integer',
                'items' => 'required|array|min:1',
                'remarks' => 'nullable|string',
                'received_amount' => 'nullable|numeric|min:0',
                'additional_received_amount' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|max:30',
                'payment_reference' => 'nullable|string|max:120',
                'payment_note' => 'nullable|string|max:255',
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
            $baseEffectiveReceived = (float) ($sale->received_amount ?? 0) - (float) ($sale->paid_amount ?? 0);

            $sale->update([
                'customer_id' => (int) $request->customer_id,
                'remarks' => $request->input('remarks', $request->input('remark', $sale->remarks)),
                'received_amount' => $finalReceived,
                'payment_mode' => $request->input('payment_mode', $sale->payment_mode),
                'payment_reference' => $request->input('payment_reference', $sale->payment_reference),
                'payment_note' => $request->input('payment_note', $sale->payment_note),
                'use_silver_balance' => (bool) $request->input('use_silver_balance', $sale->use_silver_balance ?? true),
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

            $resolvedRows = $incomingRows
                ->map(function ($row) use ($companyId) {
                    $itemSet = $this->resolveSaleUpdateItemSet($row, $companyId);
                    if (!$itemSet) {
                        return null;
                    }

                    return [
                        'row' => $row,
                        'itemset' => $itemSet,
                    ];
                })
                ->filter()
                ->unique(fn($pair) => (int) $pair['itemset']->id)
                ->values();

            if ($resolvedRows->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No valid item found. Use itemset_id/id or qr_code/code or huid/HUID.',
                ], 422);
            }

            $incomingItemsetIds = $resolvedRows
                ->map(fn($pair) => (int) $pair['itemset']->id)
                ->values();

            $existingItems = $sale->saleItems->keyBy('itemset_id');
            $existingItemsetIds = $existingItems->keys()->map(fn($id) => (int) $id)->values();

            $approvalIds = [];

            $removedItemsetIds = $existingItemsetIds->diff($incomingItemsetIds)->values();
            foreach ($removedItemsetIds as $itemsetId) {
                $saleItem = $existingItems->get($itemsetId);
                if (!$saleItem) {
                    continue;
                }

                if (!empty($saleItem->approval_item_id)) {
                    $approvalItem = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->find((int) $saleItem->approval_item_id);

                    if ($approvalItem) {
                        $approvalItem->update(['status' => 'pending']);
                        $approvalIds[] = (int) $approvalItem->approval_id;
                    }
                }

                ItemSet::where('company_id', $companyId)
                    ->where('id', (int) $itemsetId)
                    ->update(['is_sold' => 0]);

                $saleItem->delete();
            }

            $total = 0;

            foreach ($resolvedRows as $pair) {
                $row = $pair['row'];
                $itemSet = $pair['itemset'];
                $itemsetId = (int) $itemSet->id;

                $existingSaleItem = $existingItems->get($itemsetId);
                $approvalItemId = (int) ($row['approval_item_id'] ?? 0);
                $approvalItem = null;

                if ($approvalItemId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        $approvalItemId,
                        $itemsetId,
                        $approvalCustomerId > 0 ? $approvalCustomerId : null,
                        true
                    );

                    if (!$approvalItem) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Approval item not found for selected approval person.',
                        ], 422);
                    }

                    $approvalItemId = (int) $approvalItem->id;
                } elseif ($approvalCustomerId > 0) {
                    $approvalItem = $this->resolveApprovalItemForSale(
                        $companyId,
                        null,
                        $itemsetId,
                        $approvalCustomerId,
                        false
                    );

                    if ($approvalItem) {
                        $approvalItemId = (int) $approvalItem->id;
                    }
                }

                if (!$existingSaleItem && $approvalItemId <= 0 && (int) $itemSet->is_sold === 1) {
                    return response()->json([
                        'success' => false,
                        'message' => 'ItemSet not available for sale: ' . $itemsetId,
                    ], 422);
                }

                $grossWeight = (float) ($row['gross_weight'] ?? $itemSet->gross_weight ?? 0);
                $otherWeight = (float) ($row['other_weight'] ?? $itemSet->other ?? 0);
                $netWeight = (float) ($row['net_weight'] ?? max(0, $grossWeight - $otherWeight));
                $purity = (float) ($row['purity'] ?? 0);
                $wastePercent = (float) ($row['waste_percent'] ?? 0);
                $netPurity = (float) ($row['net_purity'] ?? ($purity - $wastePercent));
                $fineWeight = (float) ($row['fine_weight'] ?? ($netWeight * $netPurity / 100));
                $metalRate = (float) ($row['metal_rate'] ?? 0);
                $metalAmount = (float) ($row['metal_amount'] ?? ($fineWeight * $metalRate));
                $labourRate = (float) ($row['labour_rate'] ?? 0);
                $labourAmount = (float) ($row['labour_amount'] ?? ($netWeight * $labourRate));
                $otherAmount = (float) ($row['other_amount'] ?? 0);
                $lineTotal = (float) ($row['total_amount'] ?? ($metalAmount + $labourAmount + $otherAmount));

                $payload = [
                    'itemset_id' => $itemSet->id,
                    'approval_item_id' => $approvalItemId > 0 ? $approvalItemId : null,
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
                ];

                if ($existingSaleItem) {
                    $oldApprovalItemId = (int) ($existingSaleItem->approval_item_id ?? 0);
                    $newApprovalItemId = (int) ($approvalItemId ?? 0);
                    $existingSaleItem->update($payload);

                    if ($oldApprovalItemId > 0 && $oldApprovalItemId !== $newApprovalItemId) {
                        $oldApproval = ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                            $q->where('company_id', $companyId);
                        })->find($oldApprovalItemId);
                        if ($oldApproval) {
                            $oldApproval->update(['status' => 'pending']);
                            $approvalIds[] = (int) $oldApproval->approval_id;
                        }
                    }
                } else {
                    SaleItem::create(array_merge($payload, [
                        'sale_id' => $sale->id,
                        'product_id' => $row['product_id'] ?? $itemSet->item_id ?? null,
                        'qty' => (int) ($row['qty'] ?? 1),
                    ]));
                    $itemSet->update(['is_sold' => 1]);
                }

                if ($approvalItemId > 0) {
                    $approvalItem = $approvalItem ?: ApprovalItem::whereHas('approval', function ($q) use ($companyId) {
                        $q->where('company_id', $companyId);
                    })->find($approvalItemId);
                    if ($approvalItem) {
                        $approvalItem->update(['status' => 'sold']);
                        $approvalIds[] = (int) $approvalItem->approval_id;
                    }
                }

                $total += $lineTotal;
            }

            $sale->update(['net_total' => $total]);
            $this->syncSilverAdvanceUsageForSale(
                $companyId,
                $sale,
                (bool) ($sale->use_silver_balance ?? true),
                (int) $user->id
            );
            $sale->increment('modified_count');
            $this->refreshApprovalHeaderStatus($approvalIds);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale updated successfully',
                'data' => $sale->fresh(['customer', 'saleItems.itemset']),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
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
                    ->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']);
            })
                ->whereNotExists(function ($sub) {
                    $sub->select(DB::raw(1))
                        ->from('sale_items')
                        ->where(function ($q) {
                            $q->whereColumn('sale_items.approval_item_id', 'approval_items.id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.itemset_id')
                                ->orWhereColumn('sale_items.itemset_id', 'approval_items.item_id');
                        });
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

        return $query->first();
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

        $qrCode = trim((string) ($row['qr_code'] ?? $row['code'] ?? ''));
        if ($qrCode !== '') {
            return (clone $query)->where('qr_code', $qrCode)->first();
        }

        $huid = trim((string) ($row['huid'] ?? $row['HUID'] ?? ''));
        if ($huid !== '') {
            return (clone $query)->where('HUID', $huid)->first();
        }

        return null;
    }

    private function syncSilverAdvanceUsageForSale(int $companyId, Sale $sale, bool $useSilverBalance = false, ?int $userId = null): void
    {
        CustomerAdvanceLedger::query()
            ->where('company_id', $companyId)
            ->where('reference_type', 'sale')
            ->where('reference_id', (int) $sale->id)
            ->where('entry_type', 'purchase_adjust_metal')
            ->delete();

        if (!$useSilverBalance || (int) ($sale->customer_id ?? 0) <= 0) {
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

    public function pdf(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

            $company = Company::find($companyId);
            $sale = Sale::with(['customer', 'saleItems.itemset.item', 'payments'])
                ->where('company_id', $companyId)
                ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found'
            ], 404);
        }

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale', 'company'))
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
        $sale = Sale::with(['customer', 'saleItems.itemset.item', 'payments'])
            ->where('company_id', $companyId)
            ->find($id);

        if (!$sale) {
            return response()->json([
                'success' => false,
                'message' => 'Sale not found',
                'code' => 'NOT_FOUND',
            ], 404);
        }

        $pdf = Pdf::loadView('company.sales.invoice_pdf', compact('sale', 'company'))
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
