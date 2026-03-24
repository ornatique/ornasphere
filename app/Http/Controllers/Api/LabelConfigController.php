<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LabelConfig;
use App\Models\Item;

class LabelConfigController extends Controller
{
    // ================= LIST =================
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
    
        $configs = LabelConfig::with('item')
                    ->where('company_id', $companyId)
                    ->latest()
                    ->get();

        return response()->json([
            'success' => true,
            'data' => $configs
        ]);
    }

    // ================= SHOW =================
    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $config = LabelConfig::with('item')
                    ->where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $config
        ]);
    }

    // ================= STORE =================
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'numeric_length' => 'nullable|integer',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        // Optional: Check item belongs to same company
        $item = Item::where('id', $request->item_id)
                    ->where('company_id', $companyId)
                    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid item selected'
            ], 400);
        }

        $config = LabelConfig::updateOrCreate(
            [
                'company_id' => $companyId,
                'item_id' => $request->item_id
            ],
            [
                'prefix' => $request->prefix,
                'numeric_length' => $request->numeric_length,
                'last_no' => $request->last_no ?? 0,
                'reuse' => $request->boolean('reuse'),
                'random' => $request->boolean('random'),
                'min_no' => $request->min_no,
                'max_no' => $request->max_no,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Label Config saved successfully',
            'data' => $config
        ], 201);
    }

    // ================= UPDATE =================
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $config = LabelConfig::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config not found'
            ], 404);
        }

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'numeric_length' => 'nullable|integer',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        $config->update([
            'item_id' => $request->item_id,
            'prefix' => $request->prefix,
            'numeric_length' => $request->numeric_length,
            'last_no' => $request->last_no ?? 0,
            'reuse' => $request->boolean('reuse'),
            'random' => $request->boolean('random'),
            'min_no' => $request->min_no,
            'max_no' => $request->max_no,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Label Config updated successfully',
            'data' => $config
        ]);
    }

    // ================= DELETE =================
    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $config = LabelConfig::where('company_id', $companyId)
                    ->where('id', $id)
                    ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config not found'
            ], 404);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Label Config deleted successfully'
        ]);
    }
}