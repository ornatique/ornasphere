<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LabelConfig;
use App\Models\Item;
use App\Models\ItemSet;

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

        $usedItemIds = ItemSet::where('company_id', $companyId)
            ->whereIn('item_id', $configs->pluck('item_id'))
            ->distinct()->pluck('item_id')->flip();
        $configs->each(function ($config) use ($usedItemIds) {
            $config->setAttribute('is_in_use', $usedItemIds->has($config->item_id));
        });

        return response()->json([
            'success' => true,
            'data' => $configs,
            'available_items' => $this->availableItemsQuery($companyId)->get(['id', 'item_name'])
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
            'data' => $this->withUsageStatus($config),
            'available_items' => $this->availableItemsQuery($companyId, $config->item_id)->get(['id', 'item_name'])
        ]);
    }

    // ================= STORE =================
    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;

        $validated = $request->validate([
            'item_id' => 'required|exists:items,id',
            'prefix' => 'nullable|string|max:50',
            'numeric_length' => 'nullable|integer',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        $prefix = $this->normalizePrefix($request->input('prefix'));

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

        if (LabelConfig::where('company_id', $companyId)->where('item_id', $item->id)->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config already exists for selected item.',
                'code' => 'LABEL_CONFIG_EXISTS'
            ], 422);
        }

        if ($this->prefixExists((int) $companyId, $prefix)) {
            return response()->json([
                'success' => false,
                'message' => 'This prefix is already used. Please enter a different prefix.',
                'code' => 'LABEL_CONFIG_PREFIX_EXISTS'
            ], 422);
        }

        $config = LabelConfig::create([
            'company_id' => $companyId,
            'item_id' => $item->id,
            'prefix' => $prefix,
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
            'message' => 'Label Config saved successfully',
            'data' => $this->withUsageStatus($config)
        ], 200);
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
            'item_id' => $this->hasGeneratedLabelItems((int) $companyId, (int) $config->item_id) ? 'nullable' : 'required|exists:items,id',
            'prefix' => 'nullable|string|max:50',
            'numeric_length' => 'nullable|integer',
            'last_no' => 'nullable|integer|min:0',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        $isLocked = $this->hasGeneratedLabelItems((int) $companyId, (int) $config->item_id);
        $prefix = $this->normalizePrefix($request->input('prefix'));
        $itemId = $isLocked ? (int) $config->item_id : (int) $request->item_id;
        $lastNo = $request->has('last_no')
            ? (int) $request->last_no
            : (int) ($config->last_no ?? 0);

        $item = Item::where('id', $itemId)
                    ->where('company_id', $companyId)
                    ->first();

        if (!$item) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid item selected'
            ], 400);
        }

        if (!$isLocked && LabelConfig::where('company_id', $companyId)
            ->where('item_id', $item->id)
            ->where('id', '!=', $config->id)
            ->exists()) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config already exists for selected item.',
                'code' => 'LABEL_CONFIG_EXISTS'
            ], 422);
        }

        if (!$isLocked && $this->prefixExists((int) $companyId, $prefix, (int) $config->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This prefix is already used. Please enter a different prefix.',
                'code' => 'LABEL_CONFIG_PREFIX_EXISTS'
            ], 422);
        }

        $minimumLastNo = $this->minimumAllowedLastNo($config);
        if ($isLocked && $lastNo < $minimumLastNo) {
            return response()->json([
                'success' => false,
                'message' => "Last No cannot be less than {$minimumLastNo}. Generated label numbers cannot be reused.",
                'code' => 'LABEL_CONFIG_LAST_NO_DECREASED',
                'minimum_last_no' => $minimumLastNo,
            ], 422);
        }

        $config->update([
            'item_id' => $isLocked ? $config->item_id : $item->id,
            'prefix' => $isLocked ? $config->prefix : $prefix,
            'numeric_length' => $request->numeric_length,
            'last_no' => $lastNo,
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
            'data' => $this->withUsageStatus($config)
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

        if ($this->hasGeneratedLabelItems((int) $companyId, (int) $config->item_id)) {
            return response()->json([
                'success' => false,
                'message' => 'Label items already created for this item. This label config cannot be deleted.',
                'code' => 'LABEL_CONFIG_IN_USE'
            ], 422);
        }

        $config->delete();

        return response()->json([
            'success' => true,
            'message' => 'Label Config deleted successfully'
        ]);
    }

    private function availableItemsQuery(int $companyId, $currentItemId = null)
    {
        return Item::where('company_id', $companyId)
            ->where(function ($query) use ($companyId, $currentItemId) {
                $query->whereDoesntHave('labelConfig', function ($labelConfigQuery) use ($companyId) {
                    $labelConfigQuery->where('company_id', $companyId);
                })->when($currentItemId, fn($q) => $q->orWhere('id', $currentItemId));
            })
            ->orderBy('item_name');
    }

    private function normalizePrefix($prefix): ?string
    {
        $prefix = trim((string) $prefix);

        return $prefix === '' ? null : $prefix;
    }

    private function prefixExists(int $companyId, ?string $prefix, ?int $ignoreId = null): bool
    {
        if ($prefix === null) {
            return false;
        }

        return LabelConfig::where('company_id', $companyId)
            ->whereRaw('LOWER(TRIM(prefix)) = ?', [strtolower($prefix)])
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }

    private function withUsageStatus(LabelConfig $config): LabelConfig
    {
        $config->setAttribute('is_in_use', $this->hasGeneratedLabelItems(
            (int) $config->company_id, (int) $config->item_id
        ));

        return $config;
    }

    private function hasGeneratedLabelItems(int $companyId, int $itemId): bool
    {
        return ItemSet::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->exists();
    }

    private function minimumAllowedLastNo(LabelConfig $labelConfig): int
    {
        $highestExistingSerialNo = (int) ItemSet::where('company_id', $labelConfig->company_id)
            ->where('item_id', $labelConfig->item_id)
            ->where('is_final', 1)
            ->max('serial_no');

        return max((int) ($labelConfig->last_no ?? 0), $highestExistingSerialNo);
    }
}
