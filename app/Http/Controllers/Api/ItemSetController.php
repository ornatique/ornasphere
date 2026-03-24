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
//     public function saveCell(Request $request)
// {
//     $companyId = $request->user()->company_id;

//     $request->validate([
//         'item_id' => 'required|exists:items,id',
//     ]);

//     if ($request->id) {

//         $set = ItemSet::where('company_id', $companyId)
//             ->where('id', $request->id)
//             ->where('is_final', 0)
//             ->first();

//         if (!$set) {
//             return response()->json([
//                 'success' => false,
//                 'message' => 'Row not found'
//             ], 404);
//         }

//         $set->update([
//             'gross_weight' => $request->gross_weight,
//             'other' => $request->other,
//             'net_weight' => $request->net_weight,
//             'labour_rate' => $request->labour_rate,
//             'labour_amount' => $request->labour_amount,
//             'size' => $request->size,
//             'HUID' => $request->HUID,
//         ]);

//     } else {

//         $set = ItemSet::create([
//             'company_id' => $companyId,
//             'item_id' => $request->item_id,
//             'gross_weight' => $request->gross_weight,
//             'other' => $request->other,
//             'net_weight' => $request->net_weight,
//             'labour_rate' => $request->labour_rate,
//             'labour_amount' => $request->labour_amount,
//             'size' => $request->size,
//             'HUID' => $request->HUID,
//             'is_final' => 0
//         ]);
//     }

//     return response()->json([
//         'success' => true,
//         'message' => 'Saved successfully',
//         'data' => $set
//     ]);
// }

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

        $set = ItemSet::create([
            'company_id' => $companyId,
            'item_id' => $request->item_id,
            'gross_weight' => $row['gross_weight'] ?? null,
            'other' => $row['other'] ?? null,
            'net_weight' => $row['net_weight'] ?? null,
            'labour_rate' => $row['labour_rate'] ?? null,
            'labour_amount' => $row['labour_amount'] ?? null,
            'size' => $row['size'] ?? null,
            'HUID' => $row['HUID'] ?? null,
            'is_final' => 0
        ]);

        $savedRows[] = $set;
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
            ->firstOrFail();

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

                $serialFormatted = str_pad(
                    $nextNo,
                    $config->numeric_length,
                    '0',
                    STR_PAD_LEFT
                );

                $qrText = $config->prefix . $serialFormatted;

                $set->update([
                    'serial_no' => $nextNo,
                    'qr_code'   => $qrText,
                    'barcode'   => $qrText,
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
        $query = ItemSet::with('item');

        // company filter (IMPORTANT)
        if ($request->company_id) {
            $query->where('company_id', $request->company_id);
        }

        // item filter
        if ($request->item_id) {
            $query->where('item_id', $request->item_id);
        }

        // date filter
        if ($request->from_date && $request->to_date) {
            $query->whereDate('created_at', '>=', $request->from_date)
                  ->whereDate('created_at', '<=', $request->to_date);
        }

        $data = $query->latest()->get();

        return response()->json([
            'status' => true,
            'data' => $data
        ]);
    }

    // ✅ SINGLE (EDIT)
    public function show($id)
    {
        $item = ItemSet::with('item')->findOrFail($id);

        return response()->json([
            'status' => true,
            'data' => $item
        ]);
    }

    // ✅ UPDATE
    public function update(Request $request, $id)
    {
        
        $item = ItemSet::findOrFail($id);

        $item->update([
            'gross_weight' => $request->gross_weight,
            'net_weight'   => $request->net_weight,
            'other'        => $request->other,
            'size'         => $request->size,
            'HUID'         => $request->huid,
        ]);

        return response()->json([
            'status' => true,
            'message' => 'Updated successfully'
        ]);
    }

    // ✅ DELETE
    public function destroy($id)
    {
        $item = ItemSet::findOrFail($id);
        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted successfully'
        ]);
    }
}