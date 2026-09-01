<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LabourFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class LabourFormulaApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $data = LabourFormula::where('company_id', $companyId)
            ->latest()
            ->get()
            ->map(function ($row) use ($companyId) {
                $row->is_in_use = $this->isUsedInJobwork($companyId, (int) $row->id, (string) $row->name);
                return $row;
            });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function options(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $query = LabourFormula::where('company_id', $companyId)
            ->orderBy('name');

        if ($request->boolean('active_only', true)) {
            $query->where('status', true);
        }

        $rows = $query->get(['id', 'name', 'status']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $data = LabourFormula::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Labour Formula not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('labour_formulas', 'name')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'status' => ['nullable', 'boolean'],
        ]);

        $data = LabourFormula::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Labour Formula created successfully',
            'data' => $data,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $data = LabourFormula::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Labour Formula not found',
            ], 404);
        }

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('labour_formulas', 'name')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($data->id),
            ],
            'status' => ['required', 'boolean'],
        ]);

        $data->update([
            'name' => $validated['name'],
            'status' => (bool) $validated['status'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Labour Formula updated successfully',
            'data' => $data,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $data = LabourFormula::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Labour Formula not found',
            ], 404);
        }

        if ($this->isUsedInJobwork($companyId, (int) $data->id, (string) $data->name)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this Labour Formula is already in use.',
            ], 422);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Labour Formula deleted successfully',
        ]);
    }

    private function isUsedInJobwork(int $companyId, int $labourFormulaId, string $labourFormulaName): bool
    {
        $checks = [
            ['table' => 'production_steps', 'column' => 'labour_formula_id', 'type' => 'id'],
            ['table' => 'items', 'column' => 'labour_type', 'type' => 'labour_name'],
            ['table' => 'item_sets', 'column' => 'sale_labour_formula', 'type' => 'name'],
            ['table' => 'jobwork_issues', 'column' => 'labour_formula_id', 'type' => 'id'],
            ['table' => 'jobwork_issue_items', 'column' => 'labour_formula_id', 'type' => 'id'],
            ['table' => 'jobwork_issues', 'column' => 'labour_formula', 'type' => 'name'],
            ['table' => 'jobwork_issue_items', 'column' => 'labour_formula', 'type' => 'name'],
            ['table' => 'jobwork_issues', 'column' => 'labour_formula_name', 'type' => 'name'],
            ['table' => 'jobwork_issue_items', 'column' => 'labour_formula_name', 'type' => 'name'],
        ];

        foreach ($checks as $check) {
            $table = $check['table'];
            $column = $check['column'];
            $type = $check['type'];

            if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
                continue;
            }

            $query = DB::table($table);

            if (Schema::hasColumn($table, 'company_id')) {
                $query->where('company_id', $companyId);
            }

            if ($type === 'id') {
                $query->where($column, $labourFormulaId);
            } elseif ($type === 'labour_name') {
                $query->where(function ($q) use ($column, $labourFormulaName) {
                    $q->where($column, $labourFormulaName)
                        ->orWhere($column, $this->labourFormulaKey($labourFormulaName));
                });
            } else {
                $query->where($column, $labourFormulaName);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }

    private function labourFormulaKey(string $name): string
    {
        return match (strtolower(preg_replace('/\s+/', '', trim($name)))) {
            'pernetweight' => 'per_netweight',
            'perfineweight' => 'per_fineweight',
            'pergrossweight' => 'per_grossweight',
            'perquantity' => 'per_quantity',
            'flat' => 'flat',
            default => strtolower(str_replace(' ', '_', trim($name))),
        };
    }
}
