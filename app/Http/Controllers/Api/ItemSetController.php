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
    private function resolveIncomingRowId(array $row): ?int
    {
        $id = $row['id'] ?? ($row['temp_id'] ?? null);

        if ($id === null || $id === '') {
            return null;
        }

        return (int) $id;
    }

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

        $incomingId = $request->id ?? $request->temp_id;

        $payload = [
            'gross_weight' => $request->gross_weight,
            'other' => $request->other ?? $request->other_weight,
            'net_weight' => $request->net_weight,
            'sale_labour_rate' => $request->sale_labour_rate ?? $request->labour_rate,
            'sale_labour_amount' => $request->sale_labour_amount ?? $request->labour_amount,
            'sale_other' => $request->sale_other,
            'size' => $request->size,
            'HUID' => $request->HUID ?? $request->huid,
            'sale_labour_formula' => $request->sale_labour_formula,
            'supplier_person' => $request->supplier_person,
        ];

        if (!empty($incomingId)) {
            $set = ItemSet::where('company_id', $companyId)
                ->where('item_id', $request->item_id)
                ->where('id', $incomingId)
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
    $rows = (array) $request->rows;

    DB::beginTransaction();

    try {
        $existingDrafts = ItemSet::where('company_id', $companyId)
            ->where('item_id', $request->item_id)
            ->where('is_final', 0)
            ->orderBy('id')
            ->get()
            ->keyBy('id');

        $existingIds = $existingDrafts->keys()->all();
        $touchedExistingIds = [];

        foreach ($rows as $row) {
            $huid = $row['HUID'] ?? ($row['huid'] ?? null);

            if (
                empty($row['gross_weight']) &&
                empty($row['net_weight']) &&
                empty($row['size']) &&
                empty($huid)
            ) {
                continue;
            }

            $data = [
                'gross_weight' => $row['gross_weight'] ?? null,
                'other' => $row['other'] ?? ($row['other_weight'] ?? null),
                'net_weight' => $row['net_weight'] ?? null,
                'sale_labour_formula' => $row['sale_labour_formula'] ?? null,
                'sale_labour_rate' => $row['sale_labour_rate'] ?? ($row['labour_rate'] ?? null),
                'sale_labour_amount' => $row['sale_labour_amount'] ?? ($row['labour_amount'] ?? null),
                'sale_other' => $row['sale_other'] ?? $row['other_weight'] ?? null,
                'supplier_person' => $row['supplier_person'] ?? null,
                'size' => $row['size'] ?? null,
                'HUID' => $huid,
            ];

            $set = null;
            $incomingId = $this->resolveIncomingRowId($row) ?? 0;

            if ($incomingId > 0) {
                $set = ItemSet::where('company_id', $companyId)
                    ->where('item_id', $request->item_id)
                    ->where('id', $incomingId)
                    ->first();
            }

            if ($set) {
                $set->update($data);
                if ((int) $set->is_final === 0) {
                    $touchedExistingIds[] = (int) $set->id;
                }
            } else {
                $set = ItemSet::create(array_merge($data, [
                    'company_id' => $companyId,
                    'item_id' => $request->item_id,
                    'is_final' => 0,
                ]));
            }

            $gross = (float) ($set->gross_weight ?? 0);
            $other = (float) ($set->other ?? 0);
            if ($set->net_weight === null || $set->net_weight === '') {
                $set->net_weight = max(0, $gross - $other);
                $set->save();
            }

            $savedRows[] = array_merge(
                $set->fresh()->toArray(),
                [
                    'temp_id' => $row['temp_id'] ?? null,
                ]
            );
        }

        // Remove stale old draft rows that were not part of this save payload.
        $staleIds = array_values(array_diff($existingIds, array_unique($touchedExistingIds)));
        if (!empty($staleIds)) {
            ItemSet::where('company_id', $companyId)
                ->where('item_id', $request->item_id)
                ->where('is_final', 0)
                ->whereIn('id', $staleIds)
                ->delete();
        }

        DB::commit();

        return response()->json([
            'success' => true,
            'message' => 'Multiple rows saved successfully',
            'data' => $savedRows
        ]);
    } catch (\Exception $e) {
        DB::rollBack();

        return response()->json([
            'success' => false,
            'message' => $e->getMessage(),
        ], 500);
    }
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
                ->where(function ($q) {
                    $q->whereNotNull('gross_weight')
                        ->orWhereNotNull('net_weight')
                        ->orWhereNotNull('size')
                        ->orWhereNotNull('HUID');
                })
                ->orderBy('id')
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
