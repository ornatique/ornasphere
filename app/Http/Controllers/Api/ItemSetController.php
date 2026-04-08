<?php
namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Item;
use App\Models\ItemSet;
use App\Models\LabelConfig;
use Illuminate\Support\Facades\DB;

class ItemSetController extends Controller
{
    // ================= DEFAULT LOAD GRID =================
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $itemId = $request->item_id;
            //dd($request);
        $sets = ItemSet::where('company_id', $companyId)
            ->where('item_id', $itemId)
            ->where('is_final', 0)
            ->orderBy('id')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sets
        ]);
    }

    // ================= AUTO SAVE CELL =================
    public function saveCell(Request $request)
    {
        $companyId = $request->user()->company_id;
        $toNumber = function ($value): float {
            if ($value === null || $value === '') {
                return 0;
            }
            return (float) str_replace(',', '', (string) $value);
        };

        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        $payload = [
            'gross_weight' => $request->gross_weight,
            'other' => $request->other,
            'net_weight' => $request->net_weight,
            'sale_labour_rate' => $request->sale_labour_rate ?? $request->labour_rate,
            'sale_labour_amount' => $request->sale_labour_amount ?? $request->labour_amount,
            'sale_other' => $request->sale_other,
            'size' => $request->size,
            'HUID' => $request->HUID ?? $request->huid,
        ];

        if ($request->id) {
            $set = ItemSet::where('company_id', $companyId)
                ->where('item_id', $request->item_id)
                ->where('id', $request->id)
                ->where('is_final', 0)
                ->first();

            if (!$set) {
                return response()->json([
                    'success' => false,
                    'message' => 'Row not found'
                ], 404);
            }

            $set->update($payload);
        } else {
            $set = ItemSet::create(array_merge($payload, [
                'company_id' => $companyId,
                'item_id' => $request->item_id,
                'is_final' => 0,
            ]));
        }

        // Keep net_weight in sync if gross/other passed without net_weight.
        if ($request->hasAny(['gross_weight', 'other']) && !$request->filled('net_weight')) {
            $gross = $toNumber($request->gross_weight ?? $set->gross_weight);
            $other = $toNumber($request->other ?? $set->other);
            $set->net_weight = max(0, $gross - $other);
            $set->save();
        }

        return response()->json([
            'success' => true,
            'message' => 'Saved successfully',
            'data' => $set
        ]);
    }

public function bulkSave(Request $request)
{
    $companyId = $request->user()->company_id;

    $request->validate([
        'item_id' => 'required|exists:items,id',
        'rows' => 'required|array'
    ]);

        $savedRows = [];

        foreach ($request->rows as $row) {

        if (
            empty($row['gross_weight']) &&
            empty($row['net_weight']) &&
            empty($row['size']) &&
            empty($row['HUID'])
        ) {
            continue; // skip empty rows
        }

        $data = [
            'gross_weight' => $row['gross_weight'] ?? null,
            'other' => $row['other'] ?? null,
            'net_weight' => $row['net_weight'] ?? null,
            'sale_labour_rate' => $row['sale_labour_rate'] ?? ($row['labour_rate'] ?? null),
            'sale_labour_amount' => $row['sale_labour_amount'] ?? ($row['labour_amount'] ?? null),
            'sale_other' => $row['sale_other'] ?? null,
            'size' => $row['size'] ?? null,
            'HUID' => $row['HUID'] ?? ($row['huid'] ?? null),
        ];

        if (!empty($row['id'])) {
            $set = ItemSet::where('company_id', $companyId)
                ->where('item_id', $request->item_id)
                ->where('id', $row['id'])
                ->where('is_final', 0)
                ->first();

            if ($set) {
                $set->update($data);
            } else {
                $set = ItemSet::create(array_merge($data, [
                    'company_id' => $companyId,
                    'item_id' => $request->item_id,
                    'is_final' => 0,
                ]));
            }
        } else {
            $set = ItemSet::create(array_merge($data, [
                'company_id' => $companyId,
                'item_id' => $request->item_id,
                'is_final' => 0,
            ]));
        }

        $savedRows[] = $set;
        }

        // Ensure net_weight is present for all saved rows.
        foreach ($savedRows as $saved) {
            $gross = (float) ($saved->gross_weight ?? 0);
            $other = (float) ($saved->other ?? 0);
            if ($saved->net_weight === null || $saved->net_weight === '') {
                $saved->net_weight = max(0, $gross - $other);
                $saved->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Multiple rows saved successfully',
            'data' => $savedRows
        ]);
}

    // ================= FINAL SAVE =================
    public function finalize(Request $request)
    {
        $companyId = $request->user()->company_id;

        $item = Item::where('company_id', $companyId)
            ->where('id', $request->item_id)
            ->firstOrFail();

        $config = LabelConfig::where('company_id', $companyId)
            ->where('item_id', $item->id)
            ->first();

        if (!$config) {
            return response()->json([
                'success' => false,
                'message' => 'Label Config not found for selected item. Please create Label Config first.',
            ], 422);
        }

        DB::beginTransaction();

        try {

            $draftSets = ItemSet::where('company_id', $companyId)
                ->where('item_id', $item->id)
                ->where('is_final', 0)
                ->get();

            if ($draftSets->isEmpty()) {
                return response()->json([
                    'success' => false,
                    'message' => 'No draft rows found'
                ]);
            }

            $nextNo = $config->last_no;

            foreach ($draftSets as $set) {

                $nextNo++;

                $qrText = $config->prefix . $nextNo;

                $gross = (float) ($set->gross_weight ?? 0);
                $other = (float) ($set->other ?? 0);
                $net = ($set->net_weight === null || $set->net_weight === '')
                    ? max(0, $gross - $other)
                    : (float) $set->net_weight;

                $set->update([
                    'serial_no' => $nextNo,
                    'qr_code'   => $qrText,
                    'barcode'   => $qrText,
                    'net_weight' => $net,
                    'is_final'  => 1
                ]);
            }

            $config->update(['last_no' => $nextNo]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Finalized successfully'
            ]);

        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ]);
        }
    }
    
    public function listset_data(Request $request)
    {
        $companyId = $request->user()->company_id;

        $query = ItemSet::with('item')
            ->where('company_id', $companyId);

        if ($request->item_id) {
            $query->where('item_id', $request->item_id);
        }

        // date filter
        if ($request->from_date && $request->to_date) {
            $query->whereDate('created_at', '>=', $request->from_date)
                  ->whereDate('created_at', '<=', $request->to_date);
        }

        $data = $query->latest()->get();

        $data->transform(function ($row) {
            $row->is_printed = (int) ($row->is_printed ?? 0);
            $row->print_date_time = $row->printed_at
                ? \Carbon\Carbon::parse($row->printed_at)->format('d-m-Y h:i A')
                : null;
            return $row;
        });

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // ✅ SINGLE (EDIT)
    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = ItemSet::with('item')
            ->where('company_id', $companyId)
            ->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $item
        ]);
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = ItemSet::where('company_id', $companyId)
            ->findOrFail($id);

        $item->update([
            'gross_weight' => $request->gross_weight,
            'net_weight'   => $request->net_weight,
            'other'        => $request->other,
            'size'         => $request->size,
            'HUID'         => $request->HUID ?? $request->huid,
            'sale_labour_rate' => $request->sale_labour_rate ?? $request->labour_rate,
            'sale_labour_amount' => $request->sale_labour_amount ?? $request->labour_amount,
            'sale_other' => $request->sale_other,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully'
        ]);
    }

    // ✅ DELETE
    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $item = ItemSet::where('company_id', $companyId)
            ->findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}
