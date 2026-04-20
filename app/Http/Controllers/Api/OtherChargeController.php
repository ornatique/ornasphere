<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\OtherCharge;
use Illuminate\Support\Facades\Validator;

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

        $payload = $this->buildPayload($request);
        $validator = Validator::make($payload, [
            'other_charge' => 'required|string|max:255',
            'item_id' => 'nullable|integer|exists:items,id',
            'default_amount' => 'nullable|numeric',
            'default_weight' => 'nullable|numeric',
            'quantity_pcs' => 'nullable|numeric',
            'weight_percent' => 'nullable|numeric',
            'sale_weight_percent' => 'nullable|numeric',
            'purchase_weight_percent' => 'nullable|numeric',
            'sequence_no' => 'nullable|integer',
            'purity' => 'nullable|numeric',
            'required_purity' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = OtherCharge::create(array_merge($payload, [
            'company_id' => $companyId,
        ]));

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

        $payload = $this->buildPayload($request, $data);
        $validator = Validator::make($payload, [
            'other_charge' => 'required|string|max:255',
            'item_id' => 'nullable|integer|exists:items,id',
            'default_amount' => 'nullable|numeric',
            'default_weight' => 'nullable|numeric',
            'quantity_pcs' => 'nullable|numeric',
            'weight_percent' => 'nullable|numeric',
            'sale_weight_percent' => 'nullable|numeric',
            'purchase_weight_percent' => 'nullable|numeric',
            'sequence_no' => 'nullable|integer',
            'purity' => 'nullable|numeric',
            'required_purity' => 'nullable|numeric',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
                'errors' => $validator->errors(),
            ], 422);
        }

        $data->update($payload);

        return response()->json([
            'success' => true,
            'message' => 'Other Charge updated successfully',
            'data' => $data
        ]);
    }

    private function buildPayload(Request $request, ?OtherCharge $existing = null): array
    {
        $otherCharge = trim((string) $this->firstInput($request, ['other_charge', 'name', 'charge_name'], $existing?->other_charge));

        return [
            'other_charge' => $otherCharge,
            'code' => $this->nullIfBlank($this->firstInput($request, ['code', 'charge_code'], $existing?->code)),
            'default_amount' => $this->nullableNumber($this->firstInput($request, ['default_amount', 'amount'], $existing?->default_amount)),
            'default_weight' => $this->nullableNumber($this->firstInput($request, ['default_weight', 'weight'], $existing?->default_weight)),
            'quantity_pcs' => $this->nullableNumber($this->firstInput($request, ['quantity_pcs', 'qty'], $existing?->quantity_pcs)),
            'weight_formula' => $this->nullIfBlank($this->firstInput($request, ['weight_formula', 'wt_formula'], $existing?->weight_formula)),
            'weight_percent' => $this->nullableNumber($this->firstInput($request, ['weight_percent', 'wt_percent'], $existing?->weight_percent)),
            'sale_weight_percent' => $this->nullableNumber($this->firstInput($request, ['sale_weight_percent'], $existing?->sale_weight_percent)),
            'purchase_weight_percent' => $this->nullableNumber($this->firstInput($request, ['purchase_weight_percent'], $existing?->purchase_weight_percent)),
            'sequence_no' => $this->nullableInteger($this->firstInput($request, ['sequence_no'], $existing?->sequence_no)),
            'item_id' => $this->nullableInteger($this->firstInput($request, ['item_id'], $existing?->item_id)),
            'remarks' => $this->nullIfBlank($this->firstInput($request, ['remarks', 'remark'], $existing?->remarks)),

            'is_default' => $this->boolInput($request, ['is_default'], (bool) ($existing?->is_default ?? false)),
            'is_selected' => $this->boolInput($request, ['is_selected'], (bool) ($existing?->is_selected ?? false)),
            'diamond' => $this->boolInput($request, ['diamond'], (bool) ($existing?->diamond ?? false)),
            'stone' => $this->boolInput($request, ['stone'], (bool) ($existing?->stone ?? false)),
            'stock_effect' => $this->boolInput($request, ['stock_effect'], (bool) ($existing?->stock_effect ?? false)),
            'other_charge_ol' => $this->boolInput($request, ['other_charge_ol'], (bool) ($existing?->other_charge_ol ?? false)),
            'carat_weight_auto_conversion' => $this->boolInput($request, ['carat_weight_auto_conversion'], (bool) ($existing?->carat_weight_auto_conversion ?? false)),
            'party_account_effect' => $this->boolInput($request, ['party_account_effect'], (bool) ($existing?->party_account_effect ?? false)),

            'other_amt_formula' => $this->nullIfBlank($this->firstInput($request, ['other_amt_formula', 'amt_formula'], $existing?->other_amt_formula)),
            'purity' => $this->nullableNumber($this->firstInput($request, ['purity'], $existing?->purity)),
            'required_purity' => $this->nullableNumber($this->firstInput($request, ['required_purity'], $existing?->required_purity)),
            'merge_other_charge' => $this->nullIfBlank($this->firstInput($request, ['merge_other_charge', 'merge'], $existing?->merge_other_charge)),
            'wt_operation' => $this->nullIfBlank($this->firstInput($request, ['wt_operation', 'weight_operation'], $existing?->wt_operation)),
        ];
    }

    private function firstInput(Request $request, array $keys, $fallback = null)
    {
        foreach ($keys as $key) {
            if ($request->exists($key)) {
                return $request->input($key);
            }
        }
        return $fallback;
    }

    private function boolInput(Request $request, array $keys, bool $fallback = false): bool
    {
        foreach ($keys as $key) {
            if ($request->exists($key)) {
                return filter_var($request->input($key), FILTER_VALIDATE_BOOLEAN);
            }
        }
        return $fallback;
    }

    private function nullableNumber($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (float) $value;
    }

    private function nullableInteger($value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        return (int) $value;
    }

    private function nullIfBlank($value): ?string
    {
        if ($value === null) {
            return null;
        }
        $v = trim((string) $value);
        return $v === '' ? null : $v;
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
