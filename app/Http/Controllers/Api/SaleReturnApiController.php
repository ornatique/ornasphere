<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

// Models
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\ItemSet;
use App\Models\Item;
use App\Models\User;
use App\Models\ReturnCart;


class SaleReturnApiController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | 1️⃣ Return List API
    |--------------------------------------------------------------------------
    */
    public function list_of_return(Request $request)
    {
        
        $user = auth()->user();

        $returns = SaleReturn::with('sale.customer')
            ->where('company_id', $user->company_id)
            ->latest()
            ->get()
            ->map(function ($return) {
                return [
                    'id' => $return->id,
                    'voucher_no' => $return->return_voucher_no,
                    'return_date' => $return->return_date,
                    'customer_name' => optional($return->sale->customer)->name,
                    'return_total' => $return->return_total,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $returns
        ]);
    }

    public function getSalesForReturn(Request $request)
    {
    $user = auth()->user();

    $sales = Sale::with(['customer', 'saleItems.itemset'])
        ->where('company_id', $user->company_id);

    // Filter by customer
    if ($request->customer_id) {
        $sales->where('customer_id', $request->customer_id);
    }

    // Filter by date range
    if ($request->from_date) {
        $sales->whereDate('sale_date', '>=', $request->from_date);
    }

    if ($request->to_date) {
        $sales->whereDate('sale_date', '<=', $request->to_date);
    }

    // Filter by item
    if ($request->item_id) {
        $sales->whereHas('saleItems.itemset', function ($q) use ($request) {
            $q->where('item_id', $request->item_id);
        });
    }

    $sales = $sales->orderByDesc('id')->get();

    $data = $sales->map(function ($sale) {

        return [
            'sale_id' => $sale->id,
            'voucher_no' => $sale->voucher_no,
            'customer_name' => optional($sale->customer)->name,
            'sale_date' => \Carbon\Carbon::parse($sale->sale_date)->format('Y-m-d'),
            'net_total' => $sale->net_total,
        ];
    });

    return response()->json([
        'success' => true,
        'data' => $data
    ]);
}
    /*
    |--------------------------------------------------------------------------
    | 2️⃣ Sale Details For Return API
    |--------------------------------------------------------------------------
    */
    public function saleDetails($saleId)
    {
       
        $user = auth()->user();

        $sale = Sale::with('saleItems.ItemSet.item', 'customer')
            ->where('company_id', $user->company_id)
            ->findOrFail($saleId);

        return response()->json([
            'success' => true,
            'data' => $sale
        ]);
    }


  public function scanProduct(Request $request)
{
    $user = auth()->user();

    $qr = $request->qr_code;
    $customerId = $request->customer_id;

    $saleItem = SaleItem::whereHas('itemset', function ($q) use ($qr) {
        $q->where('qr_code', $qr);
    })
    ->whereHas('sale', function ($q) use ($customerId) {
        $q->where('customer_id', $customerId);
    })
    ->with('itemset.item')
    ->first();

    if (!$saleItem) {
        return response()->json([
            'success'=>false,
            'message'=>'Product not sold to this customer'
        ]);
    }

    // 🚫 Prevent duplicate scan
    $exists = ReturnCart::where('sale_item_id', $saleItem->id)
        ->where('user_id', $user->id)
        ->where('company_id', $user->company_id)
        ->exists();

    if ($exists) {
        return response()->json([
            'success'=>false,
            'message'=>'Product already scanned'
        ]);
    }

    ReturnCart::create([
        'user_id'=>$user->id,
        'company_id'=>$user->company_id,
        'sale_item_id'=>$saleItem->id
    ]);

    return response()->json([
        'success'=>true,
        'message'=>'Added to return list'
    ]);
}

    public function returnCartList()
    {
        $user = auth()->user();
    
        $items = ReturnCart::with('saleItem.itemset.item')
            ->where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->latest()
            ->get()
            ->map(function ($cart) {
    
                $saleItem = $cart->saleItem;
    
                return [
                    'cart_id' => $cart->id,
                    'sale_item_id' => $saleItem->id,
                    'serial_no' => $saleItem->itemset->serial_no,
                    'qr_code' => $saleItem->itemset->qr_code,
                    'gross_weight' => $saleItem->itemset->gross_weight,
                    'net_weight' => $saleItem->itemset->net_weight,
                    'item_name' => optional($saleItem->itemset->item)->item_name,
                    'purity' => optional($saleItem->itemset->item)->purity,
                    'amount' => $saleItem->total_amount
                ];
            });
    
        return response()->json([
            'success' => true,
            'count' => $items->count(),
            'data' => $items
        ]);
    }
    
    public function removeCartItem($id)
    {
    $user = auth()->user();

    $cart = ReturnCart::where('id', $id)
        ->where('user_id', $user->id)
        ->where('company_id', $user->company_id)
        ->first();

    if (!$cart) {
        return response()->json([
            'success' => false,
            'message' => 'Item not found in return list'
        ]);
    }

    $cart->delete();

    return response()->json([
        'success' => true,
        'message' => 'Item removed from return list'
    ]);
}

public function confirmReturn(Request $request)
{
    $user = auth()->user();

    $cartItems = ReturnCart::with('saleItem.itemset')
        ->where('user_id', $user->id)
        ->where('company_id', $user->company_id)
        ->get();

    if ($cartItems->isEmpty()) {
        return response()->json([
            'success' => false,
            'message' => 'No items scanned for return'
        ]);
    }

    DB::beginTransaction();

    try {

        $saleId = $request->sale_id;

        $return = SaleReturn::create([
            'company_id' => $user->company_id,
            'sale_id' => $saleId,
            'return_voucher_no' => 'SR' . time(),
            'return_date' => now(),
            'return_total' => 0
        ]);

        $total = 0;

        foreach ($cartItems as $cart) {

            $saleItem = $cart->saleItem;

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

        // Update return total
        $return->update([
            'return_total' => $total
        ]);

        // Clear return cart
        ReturnCart::where('user_id', $user->id)
            ->where('company_id', $user->company_id)
            ->delete();

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Return completed successfully',
            'return_id' => $return->id
        ]);

    } catch (\Exception $e) {

        DB::rollback();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
}
    /*
    |--------------------------------------------------------------------------
    | 3️⃣ Store Sale Return API
    |--------------------------------------------------------------------------
    */
    public function store(Request $request)
    {
        $user = auth()->user();

        DB::beginTransaction();

        try {

            $sale = Sale::where('company_id', $user->company_id)
                ->findOrFail($request->sale_id);

            $return = SaleReturn::create([
                'company_id' => $user->company_id,
                'sale_id' => $sale->id,
                'return_voucher_no' => 'SR' . time(),
                'return_date' => now(),
                'return_total' => 0
            ]);

            $total = 0;

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

            $return->update([
                'return_total' => $total
            ]);

            // Reduce original sale total
            $sale->decrement('net_total', $total);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale return created successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }


    /*
    |--------------------------------------------------------------------------
    | 4️⃣ Return PDF Download API
    |--------------------------------------------------------------------------
    */
    public function pdf($returnId)
    {
        $user = auth()->user();

        $return = SaleReturn::with([
            'sale.customer',
            'items.saleItem.itemset.item'
        ])
            ->where('company_id', $user->company_id)
            ->findOrFail($returnId);

        $pdf = Pdf::loadView('company.returns.return_pdf', compact('return'));

        return $pdf->download('Return-' . $return->return_voucher_no . '.pdf');
    }
}