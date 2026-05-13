<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

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

    // ================= AR CONFIG =================
    public function arConfig(Request $request, $id)
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
            'data' => [
                'item_id' => $item->id,
                'item_name' => $item->item_name,
                'item_code' => $item->item_code,
                'jewellery_type' => $item->jewellery_type,
                'ar_mode' => $item->ar_mode,
                'glb_url' => $item->glb_url,
                'usdz_url' => $item->usdz_url,
                'thumbnail_url' => $item->thumbnail_url,
                'deepar_effect_id' => $item->deepar_effect_id,
                'ar_meta' => $item->ar_meta ? json_decode($item->ar_meta, true) : null,
            ]
        ]);
    }

    public function updateArConfig(Request $request, $id)
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
            'jewellery_type' => 'nullable|string|max:30',
            'ar_mode' => 'nullable|string|max:30',
            'glb_url' => 'nullable|string|max:2048',
            'usdz_url' => 'nullable|string|max:2048',
            'thumbnail_url' => 'nullable|string|max:2048',
            'deepar_effect_id' => 'nullable|string|max:255',
            'ar_meta' => 'nullable',
        ]);

        $arMeta = null;
        if (array_key_exists('ar_meta', $validated)) {
            if (is_array($validated['ar_meta'])) {
                $arMeta = json_encode($validated['ar_meta']);
            } else {
                $arMeta = $validated['ar_meta'];
                if (!empty($arMeta)) {
                    json_decode($arMeta, true);
                    if (json_last_error() !== JSON_ERROR_NONE) {
                        return response()->json([
                            'success' => false,
                            'message' => 'ar_meta must be valid JSON'
                        ], 422);
                    }
                }
            }
        }

        $item->update([
            'jewellery_type' => $validated['jewellery_type'] ?? $item->jewellery_type,
            'ar_mode' => $validated['ar_mode'] ?? $item->ar_mode,
            'glb_url' => $validated['glb_url'] ?? $item->glb_url,
            'usdz_url' => $validated['usdz_url'] ?? $item->usdz_url,
            'thumbnail_url' => $validated['thumbnail_url'] ?? $item->thumbnail_url,
            'deepar_effect_id' => $validated['deepar_effect_id'] ?? $item->deepar_effect_id,
            'ar_meta' => $arMeta ?? $item->ar_meta,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'AR config updated successfully',
            'data' => $item->fresh(),
        ]);
    }

    public function arCatalog(Request $request)
    {
        $companyId = $request->user()->company_id;

        $items = Item::where('company_id', $companyId)
            ->where(function ($query) {
                $query->whereNotNull('glb_url')
                    ->orWhereNotNull('deepar_effect_id');
            })
            ->select([
                'id',
                'item_name',
                'item_code',
                'jewellery_type',
                'ar_mode',
                'thumbnail_url',
                'glb_url',
                'usdz_url',
                'deepar_effect_id'
            ])
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $items,
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
            'jewellery_type' => $request->jewellery_type,
            'ar_mode' => $request->ar_mode ?? '3d_view',
            'glb_url' => $request->glb_url,
            'usdz_url' => $request->usdz_url,
            'thumbnail_url' => $request->thumbnail_url,
            'deepar_effect_id' => $request->deepar_effect_id,
            'ar_meta' => $request->ar_meta,
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

        $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'required|string|max:255',
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
            'jewellery_type' => $request->jewellery_type,
            'ar_mode' => $request->ar_mode,
            'glb_url' => $request->glb_url,
            'usdz_url' => $request->usdz_url,
            'thumbnail_url' => $request->thumbnail_url,
            'deepar_effect_id' => $request->deepar_effect_id,
            'ar_meta' => $request->ar_meta,
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
