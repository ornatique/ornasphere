<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VacuumProcess;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacuumProcessApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = VacuumProcess::query()
            ->where('company_id', $companyId)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function options(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = VacuumProcess::where('company_id', $companyId)
            ->orderBy('name')
            ->get(['id', 'name']);

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $data = $this->findForCompany($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Process not found',
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
        $userId = (int) $request->user()->id;

        $validated = $this->validatePayload($request, $companyId);

        $data = VacuumProcess::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'modified_count' => 0,
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Process created successfully',
            'data' => $data,
        ], 200);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findForCompany($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Process not found',
            ], 404);
        }

        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatePayload($request, $companyId, (int) $data->id);

        $data->update([
            'updated_by' => (int) $request->user()->id,
            'modified_count' => ((int) $data->modified_count) + 1,
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Process updated successfully',
            'data' => $data->fresh(),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $data = $this->findForCompany($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Process not found',
            ], 404);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Process deleted successfully',
        ]);
    }

    private function validatePayload(Request $request, int $companyId, ?int $id = null): array
    {
        if ($request->has('name')) {
            $request->merge([
                'name' => trim((string) $request->input('name')),
            ]);
        }

        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vacuum_processes', 'name')
                    ->where(fn($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
        ], [
            'name.unique' => 'This process name already exists.',
            'name.required' => 'Process name is required.',
        ]);
    }

    private function findForCompany(Request $request, int $id): ?VacuumProcess
    {
        return VacuumProcess::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->first();
    }
}
