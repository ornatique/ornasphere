<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\ItemSet;
use App\Models\User;
use App\Models\SaleCart;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;


class SaleApiController extends Controller
{

    // ================= LIST SALES =================
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $sales = Sale::with('customer')
            ->where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales
        ]);
    }
     public function customerlist(Request $request)
    {
        $companyId = $request->user()->company_id;
      
       $customers = User::where('company_id', $companyId)
            ->where('role', 'Customer')
             ->where('is_active', 1)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $customers
        ]);
    }
    
    public function addToCart(Request $request)
    {
        $user = auth()->user(); // 🔐 logged-in user from token
    
        $item = Itemset::where('qr_code', $request->qr_code)
            ->where('is_sold', 0)
            ->first();
    
        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item already sale'
            ]);
        }
    
        // ✅ CHECK DUPLICATE IN CART
        $exists = SaleCart::where('user_id', $user->id)
            ->where('company_id', $user->company_id) // ✅ from token
            ->where('itemset_id', $item->id)
            ->exists();
    
        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'Product already added. Please add different product.'
            ]);
        }
    
        // ✅ SAVE TO CART
        SaleCart::create([
            'user_id'    => $user->id,
            'company_id' => $user->company_id, // ✅ from token
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

    $items = SaleCart::with('itemset.item') // ✅ nested relation
        ->where('user_id', $user->id)
        ->where('company_id', $user->company_id)
        ->get();

    return response()->json($items);
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
                'item_name' => $set->item ? $set->item->item_name : 'N/A', // ✅ FIXED
                'serial_no' => $set->serial_no,
                'qr_code' => $set->qr_code,
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
    
        // selected IDs from mobile
        $ids = $request->ids; // [25,26,27]
    
        $items = ItemSet::with('item:id,item_name')
            ->where('company_id', $user->company_id)
            ->whereIn('id', $ids)
            ->get();
    
        $qrData = [];
    
        foreach ($items as $set) {
    
            $builder = new Builder(
                writer: new PngWriter(),
                data: $set->qr_code,
                size: 200,
                margin: 10
            );
    
            $result = $builder->build();
            $base64 = base64_encode($result->getString());
    
            $qrData[] = [
                'item_name' => optional($set->item)->item_name,
                'serial_no' => $set->serial_no,
                'qr_image' => 'data:image/png;base64,' . $base64,
            ];
        }
    
        $pdf = Pdf::loadView('pdf.qr_codes', compact('qrData'));
    
        return $pdf->download('qr-codes.pdf');
    }
    public function confirmSale(Request $request)
    {
        DB::beginTransaction();
    
        try {
            $cartItems = SaleCart::where('user_id', auth()->id())->get();
    
            if ($cartItems->isEmpty()) {
                return response()->json(['success' => false, 'message' => 'Cart empty']);
            }
    
            $sale = Sale::create([
                'company_id'  => $request->company_id,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);
    
            $total = 0;
    
            foreach ($cartItems as $cart) {
    
               $item = Itemset::with('item')->find($cart->itemset_id);
            //   dd($item);
                $purity = optional($item->item)->outward_purity;
                 
                SaleItem::create([
                    'sale_id'    => $sale->id,
                    'itemset_id' => $item->id,
                    'gross_weight' => $item->gross_weight,
                    'net_weight'   => $item->net_weight,
                    'purity'       => $purity,
                    'fine_weight'  => $item->fine_weight,
                    'total_amount' => $item->other,
                ]);
    
                $item->update(['is_sold' => 1]);
    
                $total += $item->other_amount;
            }
    
            $sale->update(['net_total' => $total]);
    
            // 🧹 CLEAR TEMP TABLE
            SaleCart::where('user_id', auth()->id())->delete();
    
            DB::commit();
    
            return response()->json(['success' => true]);
    
        } catch (\Exception $e) {
            DB::rollback();
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
    // ================= GET ITEMSET (QR SCAN) =================
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

    // ================= CREATE SALE =================
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        DB::beginTransaction();

        try {

            $sale = Sale::create([
                'company_id'  => $companyId,
                'customer_id' => $request->customer_id,
                'voucher_no'  => 'SL' . time(),
                'sale_date'   => now(),
                'net_total'   => 0
            ]);

            $total = 0;

            foreach ($request->items as $item) {

                $itemSet = ItemSet::findOrFail($item['itemset_id']);

                SaleItem::create([
                    'sale_id'      => $sale->id,
                    'itemset_id'   => $itemSet->id,
                    'gross_weight' => $itemSet->gross_weight,
                    'net_weight'   => $item['net_weight'],
                    'purity'       => $item['purity'],
                    'fine_weight'  => $item['fine_weight'],
                    'total_amount' => $item['amount'],
                ]);

                $itemSet->update(['is_sold' => 1]);

                $total += $item['amount'];
            }

            $sale->update(['net_total' => $total]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Sale created successfully',
                'data' => $sale
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // ================= SALE DETAILS =================
    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $sale = Sale::with('customer','saleItems.itemset')
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
}