<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ApprovalItem;
use App\Models\ApprovalHeader;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Yajra\DataTables\Facades\DataTables;
use DB;
use App\Models\Customer;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Crypt;
use App\Services\TransactionItemGuard;

class SaleReturnController extends Controller
{

    /*
    |--------------------------------------------------------------------------
    | Return List (Yajra DataTable)
    |--------------------------------------------------------------------------
    */

    public function index(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        if ($request->ajax()) {

            $returns = SaleReturn::with([
                'approval.customer',
                'approval.creator',
                'approval.items.itemSet.item',
                'approval.items.item',
                'items.approvalItem.approval.customer',
                'items.approvalItem.approval.creator',
                'items.approvalItem.itemSet.item',
                'items.approvalItem.legacyItemSet.item',
                'items.approvalItem.item',
                'items.itemSet.item',
            ])
                ->where('company_id', $company->id)
                ->where('source_type', 'approval');

            if ($request->filled('customer_id')) {
                $customerId = (int) $request->customer_id;
                $returns->where(function ($query) use ($customerId) {
                    $query->whereHas('approval', function ($approvalQuery) use ($customerId) {
                        $approvalQuery->where('customer_id', $customerId);
                    })->orWhereHas('items.approvalItem.approval', function ($approvalQuery) use ($customerId) {
                        $approvalQuery->where('customer_id', $customerId);
                    });
                });
            }

            [$fromDate, $toDate] = $this->resolveReturnDateRange($request);
            $returns->whereBetween('return_date', [$fromDate, $toDate]);

            $returns = $returns->latest()
                ->get()
                ->map(fn(SaleReturn $return) => $this->summarizeApprovalReturn($return));

            $summary = [
                'total_qty' => (int) $returns->sum(fn($row) => (int) ($row['qty'] ?? 0)),
                'total_gross_wt' => (float) $returns->sum(fn($row) => (float) ($row['gross_wt'] ?? 0)),
                'total_net_wt' => (float) $returns->sum(fn($row) => (float) ($row['net_wt'] ?? 0)),
            ];

            return DataTables::of($returns)

                ->addIndexColumn()

                ->addColumn('action', function ($return) use ($company) {
                    $encryptedReturnId = Crypt::encryptString((string) $return['return_id']);

                    $viewUrl = route('company.returns.show', [
                        'slug' => $company->slug,
                        'encryptedReturnId' => $encryptedReturnId
                    ]);

                    $pdfUrl = route('company.returns.pdf', [
                        'slug' => $company->slug,
                        'encryptedReturnId' => $encryptedReturnId
                    ]);

                    $deleteUrl = route('company.returns.destroy', [
                        'slug' => $company->slug,
                        'encryptedReturnId' => $encryptedReturnId
                    ]);

                    return '
                    <a href="' . $viewUrl . '" class="btn btn-sm btn-warning">
                       Edit
                    </a>
                    <a href="' . $pdfUrl . '" 
                       class="btn btn-sm btn-info"
                       target="_blank">
                       View PDF
                    </a>
                    <form action="' . $deleteUrl . '" method="POST" class="d-inline"
                        onsubmit="return confirm(\'Delete this approval return and move items back to approval?\')">
                        ' . csrf_field() . method_field('DELETE') . '
                        <button type="submit" class="btn btn-sm btn-danger">Delete</button>
                    </form>
                ';
                })

                ->rawColumns(['action'])
                ->with('summary', $summary)
                ->make(true);
        }

        $customers = Customer::where('company_id', $company->id)
            ->saleParties()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view('company.returns.index', compact('company', 'customers'));
    }

    public function exportListPdf(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        [$fromDate, $toDate] = $this->resolveReturnDateRange($request);

        $query = SaleReturn::with([
            'approval.customer',
            'approval.creator',
            'approval.items.itemSet.item',
            'approval.items.item',
            'items.approvalItem.approval.customer',
            'items.approvalItem.approval.creator',
            'items.approvalItem.itemSet.item',
            'items.approvalItem.legacyItemSet.item',
            'items.approvalItem.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $company->id)
            ->where('source_type', 'approval')
            ->whereBetween('return_date', [$fromDate, $toDate])
            ->latest('return_date')
            ->latest('id');

        if ($request->filled('customer_id')) {
            $customerId = (int) $request->customer_id;
            $query->where(function ($returnQuery) use ($customerId) {
                $returnQuery->whereHas('approval', function ($approvalQuery) use ($customerId) {
                    $approvalQuery->where('customer_id', $customerId);
                })->orWhereHas('items.approvalItem.approval', function ($approvalQuery) use ($customerId) {
                    $approvalQuery->where('customer_id', $customerId);
                });
            });
        }

        $rows = $query->get()
            ->map(fn(SaleReturn $return) => $this->summarizeApprovalReturn($return))
            ->values();

        return Pdf::loadView('company.returns.list_pdf', compact('company', 'rows', 'fromDate', 'toDate'))
            ->setPaper('a4', 'landscape')
            ->stream('approval-return-list-' . now()->format('YmdHis') . '.pdf');
    }

    private function resolveReturnDateRange(Request $request): array
    {
        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $today = now()->toDateString();

        if (empty($fromDate) && empty($toDate)) {
            return [$today, $today];
        }

        return [$fromDate ?: $toDate, $toDate ?: $fromDate];
    }

    private function summarizeApprovalReturn(SaleReturn $return): array
    {
        if (isset($return->approvalReturnSummary)) {
            return $return->approvalReturnSummary;
        }

        $approvalItemsByItemSet = collect(optional($return->approval)->items ?? [])
            ->filter(fn($item) => !empty($item->itemset_id))
            ->keyBy('itemset_id');
        $remainingApprovalItems = collect(optional($return->approval)->items ?? [])
            ->where('status', 'returned')
            ->values();
        $itemNames = collect();
        $totals = [
            'qty' => 0,
            'gross_wt' => 0.0,
            'net_wt' => 0.0,
            'fine_wt' => 0.0,
            'metal_amt' => 0.0,
            'labour_amt' => 0.0,
            'other_amt' => 0.0,
            'total_amt' => 0.0,
        ];

        foreach ($return->items as $returnItem) {
            $approvalItem = $returnItem->approvalItem;
            $itemSet = $returnItem->itemSet;
            if (!$approvalItem && $itemSet) {
                $approvalItem = $approvalItemsByItemSet->get($itemSet->id);
            }

            if (!$approvalItem && $itemSet) {
                $approvalItem = ApprovalItem::with(['approval.customer', 'approval.creator', 'itemSet.item', 'legacyItemSet.item', 'item'])
                    ->where(function ($query) use ($itemSet) {
                        $query->where('itemset_id', (int) $itemSet->id)
                            ->orWhere('item_id', (int) $itemSet->id);
                    })
                    ->where('status', 'returned')
                    ->whereHas('approval', fn($query) => $query->where('company_id', (int) $return->company_id))
                    ->latest('id')
                    ->first();
            }

            if (!$approvalItem) {
                $approvalItem = $remainingApprovalItems->first(function ($item) use ($returnItem) {
                    return abs((float) ($item->total_amount ?? 0) - (float) ($returnItem->return_amount ?? 0)) < 0.01;
                }) ?? $remainingApprovalItems->first();
            }

            if ($approvalItem) {
                $remainingApprovalItems = $remainingApprovalItems
                    ->reject(fn($item) => (int) $item->id === (int) $approvalItem->id)
                    ->values();
            }

            $itemSet = $itemSet ?? optional($approvalItem)->itemSet ?? optional($approvalItem)->legacyItemSet;
            $itemNames->push(
                optional(optional($itemSet)->item)->item_name
                ?? optional(optional($approvalItem)->item)->item_name
            );
            $totals['qty']++;
            $totals['gross_wt'] += (float) ($approvalItem->gross_weight ?? 0);
            $totals['net_wt'] += (float) ($approvalItem->net_weight ?? 0);
            $totals['fine_wt'] += (float) ($approvalItem->total_fine_weight ?? $approvalItem->fine_weight ?? 0);
            $totals['metal_amt'] += (float) ($approvalItem->metal_amount ?? 0);
            $totals['labour_amt'] += (float) ($approvalItem->labour_amount ?? 0);
            $totals['other_amt'] += (float) ($approvalItem->other_amount ?? 0);
            $totals['total_amt'] += (float) ($returnItem->return_amount ?? 0);
        }

        return $return->approvalReturnSummary = [
            'return_id' => (int) $return->id,
            'voucher_no' => (string) ($return->return_voucher_no ?? '-'),
            'return_date' => $return->return_date
                ? \Carbon\Carbon::parse($return->return_date)->format('d-m-Y')
                : '-',
            'customer_name' => optional(optional($return->approval)->customer)->name
                ?? optional(optional(optional(optional($return->items->first())->approvalItem)->approval)->customer)->name
                ?? '-',
            'item_names' => $itemNames->filter()->unique()->implode(', ') ?: '-',
            'created_by' => optional(optional($return->approval)->creator)->name
                ?? optional(optional(optional(optional($return->items->first())->approvalItem)->approval)->creator)->name
                ?? '-',
        ] + $totals;
    }

    /*
    |--------------------------------------------------------------------------
    | Select Approval Items For Return
    |--------------------------------------------------------------------------
    */

    public function selectSale($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
            ->saleParties()
            ->where('is_active', 1)
            ->orderBy('name')
            ->get();

        return view(
            'company.returns.select_sale',
            compact('company', 'customers')
        );
    }


    /*
    |--------------------------------------------------------------------------
    | Legacy Sale Return Entry
    |--------------------------------------------------------------------------
    */

    public function create($slug, $encryptedSaleId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        return redirect()
            ->route('company.returns.selectSale', $company->slug)
            ->with('error', 'Only pending approval items can be returned from Approval Return.');
    }


    /*
    |--------------------------------------------------------------------------
    | Store Return
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, $slug, $encryptedSaleId = null)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        if ($request->has('sale_item_ids') || $request->has('approval_item_ids')) {
            return $this->processSelectedGridReturns($request, $company);
        }

        return redirect()
            ->route('company.returns.selectSale', $company->slug)
            ->with('error', 'Only pending approval items can be returned from Approval Return.');
    }


    /*
    |--------------------------------------------------------------------------
    | Return PDF
    |--------------------------------------------------------------------------
    */

    public function pdf($slug, $encryptedReturnId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $returnId = (int) Crypt::decryptString($encryptedReturnId);

        $return = SaleReturn::with([
            'sale.customer',
            'approval.customer',
            'approval.items.itemSet.item', // âœ… important
            'items.saleItem.itemset.item',
            'items.approvalItem.itemSet.item',
            'items.approvalItem.legacyItemSet.item',
            'items.approvalItem.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $company->id)
            ->findOrFail($returnId);

        $pdf = Pdf::loadView(
            'company.returns.return_pdf',
            compact('company', 'return')
        )->setPaper('a4', 'portrait');

        return $pdf->stream(
            'Return-' . $return->return_voucher_no . '.pdf'
        );
    }

    public function show($slug, $encryptedReturnId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $returnId = (int) Crypt::decryptString($encryptedReturnId);

        $return = SaleReturn::with([
            'sale.customer',
            'approval.customer',
            'approval.items.itemSet.item',
            'items.saleItem.itemset.item',
            'items.approvalItem.approval.customer',
            'items.approvalItem.itemSet.item',
            'items.approvalItem.legacyItemSet.item',
            'items.approvalItem.item',
            'items.itemSet.item',
        ])
            ->where('company_id', $company->id)
            ->findOrFail($returnId);

        return view('company.returns.show', compact('company', 'return'));
    }

    public function destroyItem($slug, $encryptedReturnId, $encryptedItemId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $returnId = (int) Crypt::decryptString($encryptedReturnId);
        $returnItemId = (int) Crypt::decryptString($encryptedItemId);

        DB::beginTransaction();

        try {
            $return = SaleReturn::with([
                'approval.items.itemSet',
                'approval.items.legacyItemSet',
            ])
                ->where('company_id', $company->id)
                ->where('source_type', 'approval')
                ->lockForUpdate()
                ->findOrFail($returnId);

            $returnItem = SaleReturnItem::with([
                'approvalItem.itemSet',
                'approvalItem.legacyItemSet',
                'itemSet',
            ])
                ->where('sale_return_id', $return->id)
                ->lockForUpdate()
                ->findOrFail($returnItemId);

            $approvalItem = $this->resolveReturnedApprovalItem($return, $returnItem, $company->id);

            if (!$approvalItem) {
                throw new \RuntimeException('Approval item not found for this return item.');
            }

            $approvalItem->update(['status' => 'pending']);

            $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet ?? $returnItem->itemSet;
            if ($itemSet) {
                $itemSet->update(['is_sold' => 1]);
            }

            $approvalId = (int) $approvalItem->approval_id;
            $returnItem->delete();

            $remainingItems = $return->items()->count();
            if ($remainingItems === 0) {
                $return->delete();
            } else {
                $return->update([
                    'return_total' => (float) $return->items()->sum('return_amount'),
                ]);
            }

            $this->refreshApprovalStatus($approvalId);

            DB::commit();

            if ($remainingItems === 0) {
                return redirect()
                    ->route('company.returns.index', $company->slug)
                    ->with('success', 'Return item removed, item moved back to approval, and empty return voucher deleted.');
            }

            return back()->with('success', 'Return item removed and moved back to approval.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }
    }

    private function resolveReturnedApprovalItem(SaleReturn $return, SaleReturnItem $returnItem, int $companyId): ?ApprovalItem
    {
        if ($returnItem->approvalItem) {
            return $returnItem->approvalItem;
        }

        $fallbackApprovalItems = collect(optional($return->approval)->items ?? []);

        if ($returnItem->itemset_id) {
            $approvalItem = $fallbackApprovalItems
                ->first(fn($item) => (int) ($item->itemset_id ?? 0) === (int) $returnItem->itemset_id);

            if ($approvalItem) {
                return $approvalItem;
            }

            $approvalItem = ApprovalItem::with(['itemSet', 'legacyItemSet'])
                ->where(function ($query) use ($returnItem) {
                    $query->where('itemset_id', (int) $returnItem->itemset_id)
                        ->orWhere('item_id', (int) $returnItem->itemset_id);
                })
                ->where('status', 'returned')
                ->whereHas('approval', fn($query) => $query->where('company_id', $companyId))
                ->latest('id')
                ->first();

            if ($approvalItem) {
                return $approvalItem;
            }
        }

        return $fallbackApprovalItems
            ->first(fn($item) => (string) $item->status === 'returned');
    }

    public function destroy($slug, $encryptedReturnId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $returnId = (int) Crypt::decryptString($encryptedReturnId);

        DB::beginTransaction();

        try {
            $return = SaleReturn::with([
                'approval.items.itemSet',
                'approval.items.legacyItemSet',
                'items.approvalItem.itemSet',
                'items.approvalItem.legacyItemSet',
                'items.itemSet',
            ])
                ->where('company_id', $company->id)
                ->where('source_type', 'approval')
                ->lockForUpdate()
                ->findOrFail($returnId);

            $approvalIds = collect();
            $fallbackApprovalItems = collect(optional($return->approval)->items ?? []);

            foreach ($return->items as $returnItem) {
                $approvalItem = $returnItem->approvalItem;

                if (!$approvalItem && $returnItem->itemset_id) {
                    $approvalItem = $fallbackApprovalItems
                        ->first(fn($item) => (int) ($item->itemset_id ?? 0) === (int) $returnItem->itemset_id);
                }

                if (!$approvalItem && $returnItem->itemset_id) {
                    $approvalItem = ApprovalItem::with(['itemSet', 'legacyItemSet'])
                        ->where(function ($query) use ($returnItem) {
                            $query->where('itemset_id', (int) $returnItem->itemset_id)
                                ->orWhere('item_id', (int) $returnItem->itemset_id);
                        })
                        ->where('status', 'returned')
                        ->whereHas('approval', fn($query) => $query->where('company_id', $company->id))
                        ->latest('id')
                        ->first();
                }

                if (!$approvalItem) {
                    $approvalItem = $fallbackApprovalItems
                        ->first(fn($item) => (string) $item->status === 'returned');
                }

                if ($approvalItem) {
                    $approvalItem->update(['status' => 'pending']);
                    $approvalIds->push((int) $approvalItem->approval_id);

                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet ?? $returnItem->itemSet;
                    if ($itemSet) {
                        $itemSet->update(['is_sold' => 1]);
                    }
                }

                $returnItem->delete();
            }

            $return->delete();

            $approvalIds->unique()->each(fn($approvalId) => $this->refreshApprovalStatus((int) $approvalId));

            DB::commit();

            return redirect()
                ->route('company.returns.index', $company->slug)
                ->with('success', 'Approval return deleted and items moved back to approval.');
        } catch (\Exception $e) {
            DB::rollBack();

            return back()->with('error', $e->getMessage());
        }
    }
    public function getSalesForReturn(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        if ($request->ajax()) {
            if (!$request->customer_id && !$request->from_date && !$request->to_date && !$request->item_id) {
                $empty = Sale::query()->whereRaw('1 = 0');
                return DataTables::of($empty)->make(true);
            }

            $sales = Sale::with(['customer', 'saleItems.itemset'])
                ->where('company_id', $company->id);

            // ðŸ”Ž Filter by customer
            if ($request->customer_id) {
                $sales->where('customer_id', $request->customer_id);
            }

            // ðŸ”Ž Filter by date range
            if ($request->from_date) {
                $sales->whereDate('sale_date', '>=', $request->from_date);
            }

            if ($request->to_date) {
                $sales->whereDate('sale_date', '<=', $request->to_date);
            }

            // ðŸ”Ž Filter by item
            if ($request->item_id) {
                $sales->whereHas('saleItems.itemset', function ($q) use ($request) {
                    $q->where('item_id', $request->item_id);
                });
            }

            $sales->orderByDesc('id');

            return DataTables::of($sales)

                ->addIndexColumn()

                ->addColumn('customer_name', function ($sale) {
                    return optional($sale->customer)->name ?? '-';
                })

                ->editColumn('sale_date', function ($sale) {
                    return \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y');
                })

                ->editColumn('net_total', function ($sale) {
                    return number_format($sale->net_total, 2);
                })

                ->addColumn('action', function ($sale) use ($company) {
                    $encryptedSaleId = Crypt::encryptString((string) $sale->id);

                    $url = route('company.returns.create', [
                        'slug' => $company->slug,
                        'encryptedSaleId' => $encryptedSaleId
                    ]);

                    return '<a href="' . $url . '" class="btn btn-sm btn-warning">
                            Return
                        </a>';
                })

                ->rawColumns(['action'])
                ->make(true);
        }
    }

    public function searchSales(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $query = trim((string) $request->search);
        $customerId = $request->customer_id;

        if (!$customerId || strlen($query) < 2) {
            return response()->json([]);
        }

        $saleItems = SaleItem::query()
            ->with([
                'sale.customer',
                'itemset.item',
            ])
            ->whereHas('sale', function ($q) use ($company, $customerId) {
                $q->where('company_id', $company->id)
                    ->where('customer_id', $customerId);
            })
            ->whereHas('itemset', function ($q) use ($company, $query) {
                $q->where('company_id', $company->id)
                    ->where('qr_code', 'like', "%{$query}%");
            })
            ->whereNotExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('sale_return_items')
                    ->whereColumn('sale_return_items.sale_item_id', 'sale_items.id');
            })
            ->latest('id')
            ->limit(10)
            ->get();

        return response()->json($saleItems->map(function ($saleItem) {
            $sale = $saleItem->sale;
            $itemset = $saleItem->itemset;
            $gross = (float) ($saleItem->gross_weight ?? 0);
            $otherWeight = (float) ($saleItem->other_weight ?? 0);
            $net = (float) ($saleItem->net_weight ?? ($gross - $otherWeight));
            $purity = (float) ($saleItem->purity ?? 0);
            $wastePercent = (float) ($saleItem->waste_percent ?? 0);
            $netPurity = (float) ($saleItem->net_purity ?? ($purity - $wastePercent));
            $fineWeight = (float) ($saleItem->fine_weight ?? (($net * $netPurity) / 100));
            $metalRate = (float) ($saleItem->metal_rate ?? 0);
            $metalAmount = (float) ($saleItem->metal_amount ?? ($net * $metalRate));
            $labourRate = (float) ($saleItem->labour_rate ?? 0);
            $labourAmount = (float) ($saleItem->labour_amount ?? ($net * $labourRate));
            $otherAmount = (float) ($saleItem->other_amount ?? 0);
            $totalAmount = (float) ($saleItem->total_amount ?? ($metalAmount + $labourAmount + $otherAmount));
            return [
                'sale_item_id' => $saleItem->id,
                'item_id' => optional($itemset)->item_id,
                'sale_id' => optional($sale)->id,
                'voucher_no' => optional($sale)->voucher_no,
                'customer' => optional(optional($sale)->customer)->name,
                'huid' => optional($itemset)->HUID,
                'qr_code' => optional($itemset)->qr_code,
                'item_name' => optional(optional($itemset)->item)->item_name,
                'gross_weight' => number_format($gross, 3, '.', ''),
                'other_weight' => number_format($otherWeight, 3, '.', ''),
                'net_weight' => number_format($net, 3, '.', ''),
                'purity' => number_format($purity, 3, '.', ''),
                'waste_percent' => number_format($wastePercent, 3, '.', ''),
                'net_purity' => number_format($netPurity, 3, '.', ''),
                'fine_weight' => number_format($fineWeight, 3, '.', ''),
                'metal_rate' => number_format($metalRate, 2, '.', ''),
                'metal_amount' => number_format($metalAmount, 2, '.', ''),
                'labour_rate' => number_format($labourRate, 2, '.', ''),
                'labour_amount' => number_format($labourAmount, 2, '.', ''),
                'other_amount' => number_format($otherAmount, 2, '.', ''),
                'total_amount' => number_format($totalAmount, 2, '.', ''),
            ];
        }));
    }

    public function processSelectedReturns(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        return $this->processSelectedGridReturns($request, $company);
    }

    private function processSelectedGridReturns(Request $request, Company $company)
    {
        $saleItemIds = collect($request->input('sale_item_ids', []))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        if ($saleItemIds->isNotEmpty()) {
            return back()->with('error', 'Only pending approval items can be returned from Approval Return.');
        }

        $approvalItemIds = collect($request->input('approval_item_ids', []))
            ->filter()
            ->map(fn($id) => (int) $id)
            ->unique()
            ->values();

        $rowPayloads = collect($request->input('row_payloads', []))
            ->map(function ($payload) {
                if (is_array($payload)) {
                    return $payload;
                }
                $decoded = json_decode((string) $payload, true);
                return is_array($decoded) ? $decoded : null;
            })
            ->filter()
            ->values();

            $payloadMap = [];
            foreach ($rowPayloads as $payload) {
            $rowType = (string) ($payload['type'] ?? '');
            $rowId = (int) ($payload['id'] ?? 0);
            if (!$rowType || !$rowId) {
                continue;
            }
                $payloadMap["{$rowType}_{$rowId}"] = [
                    'other_amount' => (float) ($payload['other_amount'] ?? 0),
                    'total_amount' => (float) ($payload['total_amount'] ?? 0),
                    'remarks' => (string) ($payload['remarks'] ?? ''),
                ];
            }

        if ($saleItemIds->isEmpty() && $approvalItemIds->isEmpty()) {
            return back()->with('error', 'Please select at least one item for return.');
        }

        DB::beginTransaction();

        try {
            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');
            $hasApprovalItemIdColumn = Schema::hasColumn('sale_return_items', 'approval_item_id');
            $total = 0;

            $saleItems = collect();
            if ($saleItemIds->isNotEmpty()) {
                $saleItems = $saleItemIds
                    ->map(fn($id) => TransactionItemGuard::lockSaleItemForReturn($company->id, (int) $id)->load(['sale', 'itemset']))
                    ->values();
            }

            $approvalItems = collect();
            if ($approvalItemIds->isNotEmpty()) {
                $approvalItems = $approvalItemIds
                    ->map(fn($id) => TransactionItemGuard::lockApprovalItemForReturn($company->id, (int) $id)->load(['approval', 'itemSet', 'legacyItemSet']))
                    ->values();
            }

            if ($saleItems->isEmpty() && $approvalItems->isEmpty()) {
                DB::rollBack();
                return back()->with('error', 'No valid items found for return.');
            }

            $saleIds = $saleItems->pluck('sale_id')->unique()->values();
            $approvalIds = $approvalItems->pluck('approval_id')->unique()->values();

            $return = SaleReturn::create([
                'company_id' => $company->id,
                'sale_id' => $saleIds->count() === 1 ? $saleIds->first() : null,
                'source_type' => $approvalIds->isNotEmpty() ? (($saleIds->isNotEmpty()) ? 'mixed' : 'approval') : 'sale',
                'source_id' => $approvalIds->count() === 1 ? $approvalIds->first() : null,
                'return_voucher_no' => 'SR' . now()->format('YmdHis') . rand(10, 99),
                'return_date' => now(),
                'return_total' => 0,
                'remarks' => $request->input('voucher_remarks'),
            ]);

            if ($saleItems->isNotEmpty()) {
                $saleTotalsBySaleId = [];

                foreach ($saleItems as $saleItem) {
                    $rowKey = 'sale_' . (int) $saleItem->id;
                    $overrideAmount = isset($payloadMap[$rowKey]['total_amount'])
                        ? (float) $payloadMap[$rowKey]['total_amount']
                        : (float) $saleItem->total_amount;

                    SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'return_amount' => $overrideAmount,
                        'remarks' => $payloadMap[$rowKey]['remarks'] ?? null,
                    ]);

                    if ($saleItem->itemset) {
                        $saleItem->itemset->update(['is_sold' => 0]);
                    }

                    $itemAmount = $overrideAmount;
                    $total += $itemAmount;

                    $saleId = (int) $saleItem->sale_id;
                    if (!isset($saleTotalsBySaleId[$saleId])) {
                        $saleTotalsBySaleId[$saleId] = 0;
                    }
                    $saleTotalsBySaleId[$saleId] += $itemAmount;
                }

                foreach ($saleTotalsBySaleId as $saleId => $saleAmount) {
                    Sale::where('company_id', $company->id)
                        ->where('id', $saleId)
                        ->decrement('net_total', $saleAmount);
                }
            }

            if ($approvalItems->isNotEmpty()) {
                foreach ($approvalItems as $approvalItem) {
                    $itemSet = $approvalItem->itemSet ?? $approvalItem->legacyItemSet;
                    if (!$itemSet) {
                        continue;
                    }

                    $rate = (float) ($itemSet->metal_rate ?? 1);
                    $calculatedAmount = (float) ($approvalItem->total_amount ?? ((float) $approvalItem->net_weight * $rate));
                    $rowKey = 'approval_' . (int) $approvalItem->id;
                    $amount = isset($payloadMap[$rowKey]['total_amount'])
                        ? (float) $payloadMap[$rowKey]['total_amount']
                        : $calculatedAmount;

                    $returnItemPayload = [
                        'sale_return_id' => $return->id,
                        'sale_item_id' => null,
                        'return_amount' => $amount,
                        'remarks' => $payloadMap[$rowKey]['remarks'] ?? null,
                    ];

                    if ($hasApprovalItemIdColumn) {
                        $returnItemPayload['approval_item_id'] = $approvalItem->id;
                    }

                    if ($hasItemsetIdColumn) {
                        $returnItemPayload['itemset_id'] = $itemSet->id;
                    }

                    if ($hasProductIdColumn) {
                        $returnItemPayload['product_id'] = $approvalItem->item_id;
                    }

                    SaleReturnItem::create($returnItemPayload);

                    $approvalItem->update([
                        'status' => 'returned',
                    ]);

                    $itemSet->update([
                        'is_sold' => 0,
                    ]);

                    $total += $amount;
                }

                foreach ($approvalIds as $approvalId) {
                    $this->refreshApprovalStatus((int) $approvalId);
                }
            }

            $return->update([
                'return_total' => $total,
            ]);

            DB::commit();

            return redirect()
                ->route('company.returns.index', $company->slug)
                ->with('success', "Return processed successfully. Voucher created: {$return->return_voucher_no}");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', $e->getMessage());
        }
    }

    private function refreshApprovalStatus(int $approvalId): void
    {
        $total = ApprovalItem::where('approval_id', $approvalId)->count();
        $done = ApprovalItem::where('approval_id', $approvalId)
            ->whereIn('status', ['sold', 'returned'])
            ->count();

        $status = $total > 0 && $total === $done ? 'closed' : 'partial';

        ApprovalHeader::where('id', $approvalId)->update([
            'status' => $status,
        ]);
    }
}

