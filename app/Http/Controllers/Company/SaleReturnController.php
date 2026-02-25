<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Yajra\DataTables\Facades\DataTables;
use DB;
use App\Models\User;

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

            $returns = SaleReturn::with('sale.customer')
                ->where('company_id', $company->id)
                ->orderByDesc('id');

            return DataTables::of($returns)

                ->addIndexColumn()

                ->addColumn('customer_name', function ($return) {
                    return optional($return->sale->customer)->name ?? '-';
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

                    $pdfUrl = route('company.returns.pdf', [
                        'slug' => $company->slug,
                        'return' => $return->id
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

        $customers = User::where('company_id', $company->id)
            ->where('role', 'customer')
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

    public function create($slug, $saleId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $sale = Sale::with('saleItems.itemset.item', 'customer')
            ->where('company_id', $company->id)
            ->findOrFail($saleId);

        return view('company.returns.create', compact('company', 'sale'));
    }


    /*
    |--------------------------------------------------------------------------
    | Store Return
    |--------------------------------------------------------------------------
    */

    public function store(Request $request, $slug, $saleId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

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

    public function pdf($slug, $returnId)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        $return = SaleReturn::with([
            'sale.customer',
            'items.saleItem.itemset.item'
        ])
            ->where('company_id', $company->id)
            ->findOrFail($returnId);

        $pdf = Pdf::loadView(
            'company.returns.return_pdf',
            compact('return')
        );

        return $pdf->stream(
            'Return-' . $return->return_voucher_no . '.pdf'
        );
    }
    public function getSalesForReturn(Request $request, $slug)
    {
        $company = Company::where('slug', $slug)->firstOrFail();

        if ($request->ajax()) {

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

                    $url = route('company.returns.create', [
                        'slug' => $company->slug,
                        'sale' => $sale->id
                    ]);

                    return '<a href="' . $url . '" class="btn btn-sm btn-warning">
                            Return
                        </a>';
                })

                ->rawColumns(['action'])
                ->make(true);
        }
    }
}
