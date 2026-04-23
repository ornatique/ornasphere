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
                'sale.customer',
                'approval.customer',
                'items.saleItem.sale.customer',
            ])
                ->where('company_id', $company->id);

            // ✅ DATE FILTER
            if ($request->from_date && $request->to_date) {

                $returns->whereBetween('return_date', [
                    $request->from_date,
                    $request->to_date
                ]);
            } elseif ($request->from_date) {

                $returns->whereDate('return_date', '>=', $request->from_date);
            } elseif ($request->to_date) {

                $returns->whereDate('return_date', '<=', $request->to_date);
            }

            $returns = $returns->latest();

            return DataTables::of($returns)

                ->addIndexColumn()

                ->addColumn('customer_name', function ($return) {

                    if ($return->sale) {
                        return optional($return->sale->customer)->name;
                    }

                    if ($return->approval) {
                        return optional($return->approval->customer)->name;
                    }

                    $saleCustomer = optional(
                        optional(
                            optional($return->items->firstWhere('sale_item_id', '!=', null))->saleItem
                        )->sale
                    )->customer;

                    if ($saleCustomer) {
                        return $saleCustomer->name;
                    }

                    return '-';
                })

                ->editColumn('return_date', function ($return) {
                    return $return->return_date
                        ? \Carbon\Carbon::parse($return->return_date)->format('d-m-Y')
                        : '-';
                })

                ->editColumn('return_total', function ($return) {
                    return '₹ ' . number_format($return->return_total, 2);
                })

                ->addColumn('action', function ($return) use ($company) {
                    $encryptedReturnId = Crypt::encryptString((string) $return->id);

                    $pdfUrl = route('company.returns.pdf', [
                        'slug' => $company->slug,
                        'encryptedReturnId' => $encryptedReturnId
                    ]);

                    return '
                    <a href="' . $pdfUrl . '" 
                       class="btn btn-sm btn-info"
                       target="_blank">
                       View PDF
                    </a>
                ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.returns.index', compact('company'));
    }

    /*
    |--------------------------------------------------------------------------
    | Select Sale For Return
    |--------------------------------------------------------------------------
    */

    public function selectSale($slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $customers = Customer::where('company_id', $company->id)
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
    | Create Return From Sale
    |--------------------------------------------------------------------------
    */

    public function create($slug, $encryptedSaleId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();
        $saleId = (int) Crypt::decryptString($encryptedSaleId);

        $sale = Sale::where('id', $saleId)
            ->where('company_id', $company->id)
            ->with('saleItems.itemset.item', 'customer')
            ->firstOrFail();

        $selectedSaleItemId = request()->query('sale_item_id');

        if ($selectedSaleItemId) {
            $exists = $sale->saleItems->contains('id', (int) $selectedSaleItemId);
            if (!$exists) {
                abort(404, 'Sale item not found for this sale');
            }
        }

        return view('company.returns.create', compact('company', 'sale', 'selectedSaleItemId'));
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

        $saleId = $encryptedSaleId ? (int) Crypt::decryptString($encryptedSaleId) : null;
        $sale = Sale::where('company_id', $company->id)
            ->findOrFail($saleId);

        DB::beginTransaction();

        try {

            $return = SaleReturn::create([
                'company_id' => $company->id,
                'sale_id' => $sale->id,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0
            ]);

            $total = 0;

            if ($request->has('return_items')) {

                foreach ($request->return_items as $saleItemId) {

                    $saleItem = SaleItem::findOrFail($saleItemId);

                    SaleReturnItem::create([
                        'sale_return_id' => $return->id,
                        'sale_item_id' => $saleItem->id,
                        'return_amount' => $saleItem->total_amount
                    ]);

                    // Restore stock
                    $saleItem->itemset->update([
                        'is_sold' => 0
                    ]);

                    $total += $saleItem->total_amount;
                }
            }

            $return->update([
                'return_total' => $total
            ]);

            // Reduce original sale total
            $sale->decrement('net_total', $total);

            DB::commit();

            return redirect()
                ->route('company.returns.index', $company->slug)
                ->with('success', 'Sale Return Created Successfully');
        } catch (\Exception $e) {

            DB::rollback();

            return back()->with('error', $e->getMessage());
        }
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
            'approval.items.itemSet.item', // ✅ important
            'items.saleItem.itemset.item',
            'items.itemSet.item',
        ])->findOrFail($returnId);

        $pdf = Pdf::loadView(
            'company.returns.return_pdf',
            compact('return')
        )->setPaper('a4', 'portrait');

        return $pdf->stream(
            'Return-' . $return->return_voucher_no . '.pdf'
        );
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

            // 🔎 Filter by customer
            if ($request->customer_id) {
                $sales->where('customer_id', $request->customer_id);
            }

            // 🔎 Filter by date range
            if ($request->from_date) {
                $sales->whereDate('sale_date', '>=', $request->from_date);
            }

            if ($request->to_date) {
                $sales->whereDate('sale_date', '<=', $request->to_date);
            }

            // 🔎 Filter by item
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
            ];
        }

        if ($saleItemIds->isEmpty() && $approvalItemIds->isEmpty()) {
            return back()->with('error', 'Please select at least one item for return.');
        }

        DB::beginTransaction();

        try {
            $hasItemsetIdColumn = Schema::hasColumn('sale_return_items', 'itemset_id');
            $hasProductIdColumn = Schema::hasColumn('sale_return_items', 'product_id');
            $total = 0;

            $saleItems = collect();
            if ($saleItemIds->isNotEmpty()) {
                $saleItems = SaleItem::with(['sale', 'itemset'])
                    ->whereIn('id', $saleItemIds)
                    ->whereHas('sale', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->get()
                    ->filter(function ($saleItem) {
                        return !SaleReturnItem::where('sale_item_id', $saleItem->id)->exists();
                    })
                    ->values();
            }

            $approvalItems = collect();
            if ($approvalItemIds->isNotEmpty()) {
                $approvalItems = ApprovalItem::with(['approval', 'itemSet'])
                    ->whereIn('id', $approvalItemIds)
                    ->where('status', 'pending')
                    ->whereHas('approval', function ($q) use ($company) {
                        $q->where('company_id', $company->id);
                    })
                    ->get()
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
                    $itemSet = $approvalItem->itemSet;
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
                    ];

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
