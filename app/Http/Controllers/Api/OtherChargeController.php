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

    // POPUP OPTIONS (same as web flow)
    public function options(Request $request)
    {
        $companyId = $request->user()->company_id;
        $itemId = (int) $request->input('item_id', 0);

        $query = OtherCharge::query()
            ->where('company_id', $companyId)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id');

        if ($itemId > 0) {
            $query->where(function ($q) use ($itemId) {
                $q->whereNull('item_id')
                    ->orWhere('item_id', 0)
                    ->orWhere('item_id', $itemId);
            });
        }

        $rows = $query->get()->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->other_charge,
                'code' => $row->code,
                'default_amount' => (float) ($row->default_amount ?? 0),
                'default_weight' => (float) ($row->default_weight ?? 0),
                'quantity_pcs' => (float) ($row->quantity_pcs ?? 1),
                'weight_formula' => $row->weight_formula,
                'weight_percent' => (float) ($row->weight_percent ?? 0),
                'other_amt_formula' => $row->other_amt_formula,
                'is_default' => (bool) $row->is_default,
                'is_selected' => (bool) $row->is_selected,
                'item_id' => $row->item_id,
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $rows
        ]);
    }

    // POPUP CALCULATION PREVIEW
    public function calculate(Request $request)
    {
        $request->validate([
            'item_weight' => 'nullable|numeric',
            'rows' => 'required|array',
        ]);

        $itemWeight = (float) ($request->input('item_weight', 0));
        $rows = collect($request->input('rows', []))->map(function ($row) use ($itemWeight) {
            $amount = (float) ($row['amount'] ?? $row['default_amount'] ?? 0);
            $qty = (float) ($row['qty'] ?? $row['quantity_pcs'] ?? 1);
            $wtPercent = (float) ($row['wt_percent'] ?? $row['weight_percent'] ?? 0);
            $wtFormula = strtolower((string) ($row['wt_formula'] ?? $row['weight_formula'] ?? 'flat'));
            $amtFormula = strtolower((string) ($row['amt_formula'] ?? $row['other_amt_formula'] ?? 'flat'));
            $selected = filter_var($row['selected'] ?? true, FILTER_VALIDATE_BOOL);

            $weight = isset($row['weight'])
                ? (float) $row['weight']
                : 0.0;

            // Weight formula handling (editable in popup row)
            if (!isset($row['weight'])) {
                if ($wtFormula === 'per_quantity' || $wtFormula === 'per qty') {
                    $weight = (float) ($row['default_weight'] ?? 0);
                } elseif ($wtFormula === 'per_weight' || $wtFormula === 'per wt') {
                    $weight = $wtPercent > 0 ? ($itemWeight * $wtPercent / 100) : $itemWeight;
                } elseif ($wtFormula === 'wt_amt' || $wtFormula === 'wt/amt') {
                    $weight = $wtPercent > 0 ? ($itemWeight * $wtPercent / 100) : $itemWeight;
                } elseif ($wtFormula === 'flat') {
                    $weight = (float) ($row['default_weight'] ?? 0);
                } else {
                    $weight = $wtPercent > 0 ? ($itemWeight * $wtPercent / 100) : $itemWeight;
                }
            }

            $totalWeight = $weight;
            if ($wtFormula === 'per_quantity' || $wtFormula === 'per qty') {
                $totalWeight = $weight * $qty;
            }

            $lineTotal = $amount;
            if ($amtFormula === 'per_quantity' || $amtFormula === 'per qty') {
                $lineTotal = $amount * $qty;
            } elseif ($amtFormula === 'per_weight' || $amtFormula === 'per wt') {
                $lineTotal = $amount * $totalWeight;
            } elseif ($amtFormula === 'wt_amt' || $amtFormula === 'wt/amt') {
                $lineTotal = $totalWeight > 0 ? $amount * $totalWeight : $amount;
            } elseif ($amtFormula === 'flat') {
                $lineTotal = $amount;
            }

            return [
                'id' => $row['id'] ?? null,
                'name' => $row['name'] ?? null,
                'amount' => $amount,
                'qty' => $qty,
                'selected' => $selected,
                'wt_formula' => $wtFormula,
                'wt_percent' => $wtPercent,
                'weight' => $weight,
                'total_weight' => $totalWeight,
                'amt_formula' => $amtFormula,
                'line_total' => round($lineTotal, 2),
            ];
        })->values();

        $selectedTotal = (float) $rows
            ->where('selected', true)
            ->sum('line_total');

        return response()->json([
            'success' => true,
            'data' => $rows,
            'charge_total' => round((float) $rows->sum('line_total'), 2),
            'selected_charge_total' => round($selectedTotal, 2),
        ]);
    }
}
