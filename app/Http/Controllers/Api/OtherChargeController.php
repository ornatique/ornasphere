<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OtherCharge;

class OtherChargeController extends Controller
{

    // LIST
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = OtherCharge::where('company_id', $companyId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    // SHOW SINGLE
    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $data = OtherCharge::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Other Charge not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }


    // STORE
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $data = OtherCharge::create([

            'company_id' => $companyId,

            'other_charge' => $request->other_charge,
            'code' => $request->code,
            'default_amount' => $request->default_amount,
            'default_weight' => $request->default_weight,
            'quantity_pcs' => $request->quantity_pcs,
            'weight_formula' => $request->weight_formula,
            'weight_percent' => $request->weight_percent,
            'sale_weight_percent' => $request->sale_weight_percent,
            'purchase_weight_percent' => $request->purchase_weight_percent,
            'sequence_no' => $request->sequence_no,
            'item_id' => $request->item_id,
            'remarks' => $request->remarks,

            'is_default' => $request->boolean('is_default'),
            'is_selected' => $request->boolean('is_selected'),
            'diamond' => $request->boolean('diamond'),
            'stone' => $request->boolean('stone'),
            'stock_effect' => $request->boolean('stock_effect'),

            'other_amt_formula' => $request->other_amt_formula,
            'other_charge_ol' => $request->other_charge_ol,
            'purity' => $request->purity,
            'required_purity' => $request->required_purity,
            'merge_other_charge' => $request->merge_other_charge,
            'wt_operation' => $request->wt_operation,
            'carat_weight_auto_conversion' => $request->carat_weight_auto_conversion,
            'party_account_effect' => $request->party_account_effect,

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Other Charge created successfully',
            'data' => $data
        ]);
    }


    // UPDATE
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $data = OtherCharge::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Other Charge not found'
            ], 404);
        }

        $data->update([

            'other_charge' => $request->other_charge,
            'code' => $request->code,
            'default_amount' => $request->default_amount,
            'default_weight' => $request->default_weight,
            'quantity_pcs' => $request->quantity_pcs,
            'weight_formula' => $request->weight_formula,
            'weight_percent' => $request->weight_percent,
            'sale_weight_percent' => $request->sale_weight_percent,
            'purchase_weight_percent' => $request->purchase_weight_percent,
            'sequence_no' => $request->sequence_no,
            'item_id' => $request->item_id,
            'remarks' => $request->remarks,

            'is_default' => $request->boolean('is_default'),
            'is_selected' => $request->boolean('is_selected'),
            'diamond' => $request->boolean('diamond'),
            'stone' => $request->boolean('stone'),
            'stock_effect' => $request->boolean('stock_effect'),

            'other_amt_formula' => $request->other_amt_formula,
            'other_charge_ol' => $request->other_charge_ol,
            'purity' => $request->purity,
            'required_purity' => $request->required_purity,
            'merge_other_charge' => $request->merge_other_charge,
            'wt_operation' => $request->wt_operation,
            'carat_weight_auto_conversion' => $request->carat_weight_auto_conversion,
            'party_account_effect' => $request->party_account_effect,

        ]);

        return response()->json([
            'success' => true,
            'message' => 'Other Charge updated successfully',
            'data' => $data
        ]);
    }


    // DELETE
    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $data = OtherCharge::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Other Charge not found'
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Other Charge deleted successfully'
        ]);
    }
}