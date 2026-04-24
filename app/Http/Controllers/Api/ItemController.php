<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    // ================= LIST =================
    public function index(Request $request)
    {
        
        $companyId = $request->user()->company_id;

        $items = Item::where('company_id', $companyId)->latest()->get();

        return response()->json([
            'success' => true,
            'data' => $items
        ]);
    }

    // ================= SHOW =================
    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = Item::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $item
        ]);
    }

    // ================= STORE =================
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'item_name' => 'required|string|max:255'
        ]);

        $item = Item::create([
            'company_id' => $companyId,
            'item_name' => $request->item_name,
            'item_code' => $request->item_code,
            'metal' => $request->metal,
            'metal_formula' => $request->metal_formula,
            'outward_carat' => $request->outward_carat,
            'inward_carat' => $request->inward_carat,
            'outward_purity' => $request->outward_purity,
            'inward_purity' => $request->inward_purity,
            'labour_type' => $request->labour_type,
            'labour_unit' => $request->labour_unit,
            'jobwork_item_type' => $request->jobwork_item_type,
            'hsn' => $request->hsn,
            'export_hsn' => $request->export_hsn,
            'numeric_length' => $request->numeric_length,
            'item_group' => $request->item_group,
            'remarks' => $request->remarks,
            'auto_load_purity' => $request->boolean('auto_load_purity'),
            'auto_create_label_purchase' => $request->boolean('auto_create_label_purchase'),
            'auto_create_label_config' => $request->boolean('auto_create_label_config'),
            'is_active' => 1,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item created successfully',
            'data' => $item
        ], 200);
    }

    // ================= UPDATE =================
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = Item::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $validated = $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => [
                'required',
                'string',
                Rule::unique('items')->ignore($item->id)
            ],
        ]);

        $item->update([
            'item_name' => $request->item_name,
            'item_code' => $request->item_code,
            'metal' => $request->metal,
            'metal_formula' => $request->metal_formula,
            'outward_carat' => $request->outward_carat,
            'inward_carat' => $request->inward_carat,
            'outward_purity' => $request->outward_purity,
            'inward_purity' => $request->inward_purity,
            'labour_type' => $request->labour_type,
            'labour_unit' => $request->labour_unit,
            'jobwork_item_type' => $request->jobwork_item_type,
            'hsn' => $request->hsn,
            'export_hsn' => $request->export_hsn,
            'numeric_length' => $request->numeric_length,
            'item_group' => $request->item_group,
            'remarks' => $request->remarks,
            'auto_load_purity' => $request->boolean('auto_load_purity'),
            'auto_create_label_purchase' => $request->boolean('auto_create_label_purchase'),
            'auto_create_label_config' => $request->boolean('auto_create_label_config'),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Item updated successfully',
            'data' => $item
        ]);
    }

    // ================= DELETE =================
    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = Item::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Item not found'
            ], 404);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }
}