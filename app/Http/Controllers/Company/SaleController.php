<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Itemset;
use App\Models\User;
use App\Models\Company;
use Yajra\DataTables\Facades\DataTables;
use Barryvdh\DomPDF\Facade\Pdf;
use DB;

class SaleController extends Controller
{

    /**
     * Display listing (Yajra DataTable)
     */


    public function index(Request $request, $slug)
    {
        // Fetch company using slug
        $company = Company::where('slug', $slug)->firstOrFail();

        // If DataTable request
        if ($request->ajax()) {

            $sales = Sale::with('customer')
                ->where('company_id', $company->id)
                ->orderByDesc('id');   // better than latest() when using select

            return DataTables::of($sales)

                ->addIndexColumn()

                ->addColumn('customer_name', function ($sale) {
                    return optional($sale->customer)->name ?? '-';
                })

                ->editColumn('sale_date', function ($sale) {
                    return $sale->sale_date
                        ? \Carbon\Carbon::parse($sale->sale_date)->format('d-m-Y')
                        : '-';
                })

                ->editColumn('net_total', function ($sale) {
                    return number_format($sale->net_total, 2);
                })

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

        // Normal page load
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
            ->where('role', 'Customer')
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

            // Get company from slug
            $company = Company::where('slug', $slug)->firstOrFail();

            $sale = Sale::create([
                'company_id'  => $company->id,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);

            $total = 0;

            foreach ($request->items as $index => $itemsetId) {

                $item = Itemset::findOrFail($itemsetId);

                // Get edited values from form
                $netWeight  = $request->net_weight[$index] ?? $item->net_weight;
                $purity     = $request->purity[$index] ?? $item->purity;
                $fineWeight = $request->fine_weight[$index] ?? $item->fine_weight;
                $amount     = $request->amount[$index] ?? $item->other_amount;

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'itemset_id'   => $item->id,
                    'gross_weight' => $item->gross_weight,
                    'net_weight'   => $netWeight,
                    'purity'       => $purity,
                    'fine_weight'  => $fineWeight,
                    'total_amount' => $amount,
                    'product_id'   => $item->product_id ?? null,
                ]);

                // mark item sold
                $item->update([
                    'is_sold' => 1
                ]);

                $total += $amount;
            }

            // update total
            $sale->update([
                'net_total' => $total
            ]);

            DB::commit();

            return redirect()
                ->route('company.sales.index', ['slug' => $company->slug])
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
}
