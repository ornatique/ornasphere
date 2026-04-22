<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ProductionStep;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductionStepApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $data = ProductionStep::where('company_id', $companyId)
            ->with(['labourFormula:id,name', 'productionCost:id,name'])
            ->withCount('users')
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    public function options(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = ProductionStep::where('company_id', $companyId)
            ->when($request->boolean('active_only', true), fn($q) => $q->where('status', true))
            ->orderBy('name')
            ->get(['id', 'name', 'status']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $data = ProductionStep::where('company_id', $companyId)
            ->with(['labourFormula:id,name', 'productionCost:id,name'])
            ->withCount('users')
            ->where('id', $id)
            ->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Production Step not found',
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
        $validated = $this->validateData($request, $companyId);

        $data = ProductionStep::create([
            'company_id' => $companyId,
            'name' => $validated['name'],
            'labour_formula_id' => $validated['labour_formula_id'] ?? null,
            'receivable_loss' => (bool) ($validated['receivable_loss'] ?? false),
            'auto_create_cost' => (bool) ($validated['auto_create_cost'] ?? false),
            'production_cost_id' => $validated['production_cost_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        $data->users()->sync($validated['assigned_user_ids'] ?? []);
        $data->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Production Step created successfully',
            'data' => $data,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;

        $data = ProductionStep::where('company_id', $companyId)->where('id', $id)->first();
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Production Step not found',
            ], 404);
        }

        $validated = $this->validateData($request, $companyId, (int) $data->id);

        $data->update([
            'name' => $validated['name'],
            'labour_formula_id' => $validated['labour_formula_id'] ?? null,
            'receivable_loss' => (bool) ($validated['receivable_loss'] ?? false),
            'auto_create_cost' => (bool) ($validated['auto_create_cost'] ?? false),
            'production_cost_id' => $validated['production_cost_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        $data->users()->sync($validated['assigned_user_ids'] ?? []);
        $data->loadCount('users');

        return response()->json([
            'success' => true,
            'message' => 'Production Step updated successfully',
            'data' => $data,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = (int) $request->user()->company_id;
        $data = ProductionStep::where('company_id', $companyId)->where('id', $id)->first();

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Production Step not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Production Step deleted successfully',
        ]);
    }

    private function validateData(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('production_steps', 'name')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'labour_formula_id' => [
                'nullable',
                'integer',
                Rule::exists('labour_formulas', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'production_cost_id' => [
                'nullable',
                'integer',
                Rule::exists('production_costs', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'receivable_loss' => ['nullable', 'boolean'],
            'auto_create_cost' => ['nullable', 'boolean'],
            'remarks' => ['nullable', 'string'],
            'status' => ['nullable', 'boolean'],
            'assigned_user_ids' => ['nullable', 'array'],
            'assigned_user_ids.*' => [
                'integer',
                Rule::exists('users', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
        ]);
    }
}
