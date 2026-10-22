<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VacuumProcess;
use App\Models\VacuumVoucher;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class VacuumProcessApiController extends Controller
{
    public function index(Request $request)
    {
        return \App\Services\CastingApiBranches::listing($request,
            fn (int $companyId) => $this->listForCompany($request, $companyId), 'Vacuum Process');
    }

    private function listForCompany(Request $request, int $companyId)
    {

        $rows = VacuumProcess::query()
            ->where('company_id', $companyId)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where('name', 'like', "%{$search}%");
            })
            ->latest()
            ->get()
            ->map(fn($row) => $this->formatRow($row, $companyId))
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function options(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);

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
            'data' => $this->formatRow($data, \App\Services\CompanyBranches::apiManagementCompanyId($request)),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
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
            'data' => $this->formatRow($data, $companyId),
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

        $companyId = \App\Services\CompanyBranches::apiManagementCompanyId($request);
        $validated = $this->validatePayload($request, $companyId, (int) $data->id);

        $data->update([
            'updated_by' => (int) $request->user()->id,
            'modified_count' => ((int) $data->modified_count) + 1,
            'name' => $validated['name'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Process updated successfully',
            'data' => $this->formatRow($data->fresh(), $companyId),
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

        if ($this->isInUse(\App\Services\CompanyBranches::apiManagementCompanyId($request), (int) $data->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This process is already used in a voucher and cannot be deleted.',
            ], 422);
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
        return VacuumProcess::where('company_id', \App\Services\CompanyBranches::apiManagementCompanyId($request))
            ->where('id', $id)
            ->first();
    }

    private function isInUse(int $companyId, int $processId): bool
    {
        return VacuumVoucher::where('company_id', $companyId)
            ->where('vacuum_process_id', $processId)
            ->exists();
    }

    private function formatRow(VacuumProcess $row, int $companyId): array
    {
        $isUsed = $this->isInUse($companyId, (int) $row->id);

        return [
            'id' => (int) $row->id,
            'company_id' => (int) $row->company_id,
            'name' => $row->name,
            'modified_count' => (int) $row->modified_count,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
            'updated_by' => $row->updated_by ? (int) $row->updated_by : null,
            'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($row->updated_at)->format('Y-m-d H:i:s'),
            'is_used' => $isUsed,
            'can_delete' => !$isUsed,
            'created_by_user' => $row->relationLoaded('createdByUser') && $row->createdByUser ? [
                'id' => (int) $row->createdByUser->id,
                'name' => $row->createdByUser->name,
            ] : null,
            'updated_by_user' => $row->relationLoaded('updatedByUser') && $row->updatedByUser ? [
                'id' => (int) $row->updatedByUser->id,
                'name' => $row->updatedByUser->name,
            ] : null,
        ];
    }
}
