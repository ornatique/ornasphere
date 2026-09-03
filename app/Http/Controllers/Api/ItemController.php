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

        $items = Item::where('company_id', $companyId)
            ->withCount(['itemSets', 'itemLabels'])
            ->when(
                $request->boolean('label_configured_only') || $request->boolean('has_label_config'),
                function ($query) use ($companyId) {
                    $query->whereHas('labelConfig', function ($labelConfigQuery) use ($companyId) {
                        $labelConfigQuery->where('company_id', $companyId);
                    });
                }
            )
            ->when(
                $request->boolean('label_unconfigured_only') || $request->boolean('without_label_config'),
                function ($query) use ($companyId) {
                    $query->whereDoesntHave('labelConfig', function ($labelConfigQuery) use ($companyId) {
                        $labelConfigQuery->where('company_id', $companyId);
                    });
                }
            )
            ->latest()
            ->get()
            ->map(function ($item) {
                $item->is_in_use = ((int) ($item->item_sets_count ?? 0) + (int) ($item->item_labels_count ?? 0)) > 0;
                $item->delete_status = $item->is_in_use ? 'in_use' : 'deletable';

                return $item;
            });

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
            ->withCount(['itemSets', 'itemLabels'])
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
            'data' => tap($item, function ($row) {
                $row->is_in_use = ((int) ($row->item_sets_count ?? 0) + (int) ($row->item_labels_count ?? 0)) > 0;
                $row->delete_status = $row->is_in_use ? 'in_use' : 'deletable';
            })
        ]);
    }

    // ================= STORE =================
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $payload = $this->itemPayload($request, $companyId);

        $request->merge($payload);
        $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'nullable|string|max:255',
            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',
            'outward_carat' => 'nullable|numeric',
            'inward_carat' => 'nullable|numeric',
            'outward_purity' => 'nullable|numeric',
            'inward_purity' => 'nullable|numeric',
            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',
            'jobwork_item_type' => 'nullable|string|max:100',
            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',
            'numeric_length' => 'nullable|integer',
            'item_group' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $item = Item::create($payload);

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

        $payload = $this->itemPayload($request, $companyId);

        $request->merge($payload);
        $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'nullable|string|max:255',
            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',
            'outward_carat' => 'nullable|numeric',
            'inward_carat' => 'nullable|numeric',
            'outward_purity' => 'nullable|numeric',
            'inward_purity' => 'nullable|numeric',
            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',
            'jobwork_item_type' => 'nullable|string|max:100',
            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',
            'numeric_length' => 'nullable|integer',
            'item_group' => 'nullable|string|max:100',
            'remarks' => 'nullable|string|max:1000',
        ]);

        $item->update($payload);

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

        if ($item->isInUseInLabelStock()) {
            return response()->json([
                'success' => false,
                'message' => 'This item is in use in label stock and cannot be deleted.',
                'code' => 'ITEM_IN_USE',
            ], 422);
        }

        $item->delete();

        return response()->json([
            'success' => true,
            'message' => 'Item deleted successfully'
        ]);
    }

    private function itemPayload(Request $request, int $companyId): array
    {
        return [
            'company_id' => $companyId,
            'item_name' => $request->input('item_name'),
            'item_code' => $request->input('item_code'),
            'metal' => $request->input('metal'),
            'metal_formula' => $request->input('metal_formula'),
            'outward_carat' => $this->nullableDecimal($request->input('outward_carat')),
            'inward_carat' => $this->nullableDecimal($request->input('inward_carat')),
            'outward_purity' => $this->nullableDecimal($request->input('outward_purity')),
            'inward_purity' => $this->nullableDecimal($request->input('inward_purity')),
            'labour_type' => $request->input('labour_type'),
            'labour_unit' => $request->input('labour_unit'),
            'jobwork_item_type' => $request->input('jobwork_item_type'),
            'hsn' => $request->input('hsn'),
            'export_hsn' => $request->input('export_hsn'),
            'numeric_length' => $this->nullableInteger($request->input('numeric_length')),
            'item_group' => $request->input('item_group'),
            'remarks' => $request->input('remarks'),
            'auto_load_purity' => $request->boolean('auto_load_purity'),
            'auto_create_label_purchase' => $request->boolean('auto_create_label_purchase'),
            'auto_create_label_config' => $request->boolean('auto_create_label_config'),
        ];
    }

    private function nullableDecimal($value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = str_replace(',', '', trim((string) $value));

        if ($value === '' || $value === '.' || $value === '-' || $value === '+') {
            return null;
        }

        return is_numeric($value) ? $value : null;
    }

    private function nullableInteger($value): ?int
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return preg_match('/^-?\d+$/', $value) ? (int) $value : null;
    }
}
