<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\VacuumBuch;
use App\Models\VacuumVoucherItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VacuumBuchApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = VacuumBuch::query()
            ->where('company_id', $companyId)
            ->with(['createdByUser:id,name', 'updatedByUser:id,name'])
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('buch_no', 'like', "%{$search}%")
                        ->orWhere('size_inch', 'like', "%{$search}%")
                        ->orWhere('weight', 'like', "%{$search}%");
                });
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
        $companyId = (int) $request->user()->company_id;

        $rows = VacuumBuch::where('company_id', $companyId)
            ->orderBy('buch_no')
            ->get(['id', 'buch_no', 'size_inch', 'weight']);

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
                'message' => 'Vacuum Buch not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatRow($data, (int) $request->user()->company_id),
        ]);
    }

    public function store(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $userId = (int) $request->user()->id;
        $payload = $this->requestPayload($request);

        if (array_key_exists('rows', $payload)) {
            return $this->storeRows($payload['rows'], $companyId, $userId);
        }

        $validated = $this->validatePayload($request, $companyId);

        $data = VacuumBuch::create([
            'company_id' => $companyId,
            'created_by' => $userId,
            'updated_by' => $userId,
            'modified_count' => 0,
            'buch_no' => $validated['buch_no'],
            'size_inch' => $validated['size_inch'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Buch created successfully',
            'data' => $this->formatRow($data, $companyId),
        ], 200);
    }

    private function storeRows($inputRows, int $companyId, int $userId)
    {
        $rows = collect(is_array($inputRows) ? $inputRows : [])
            ->map(function ($row) {
                return [
                    'buch_no' => trim((string) ($row['buch_no'] ?? '')),
                    'size_inch' => $this->nullableNumericInput($row['size_inch'] ?? null),
                    'weight' => $this->nullableNumericInput($row['weight'] ?? null),
                ];
            })
            ->filter(fn($row) => $row['buch_no'] !== '')
            ->values()
            ->all();

        $validator = validator(['rows' => $rows], [
            'rows' => ['required', 'array', 'min:1'],
            'rows.*.buch_no' => ['required', 'string', 'max:255'],
            'rows.*.size_inch' => ['nullable', 'numeric', 'min:0'],
            'rows.*.weight' => ['nullable', 'numeric', 'min:0'],
        ], [
            'rows.required' => 'Please enter at least one Buch No.',
            'rows.min' => 'Please enter at least one Buch No.',
            'rows.*.buch_no.required' => 'Buch No is required.',
        ]);

        $buchNos = collect($rows)->pluck('buch_no');
        $duplicateInRequest = $buchNos
            ->map(fn($value) => strtolower(trim((string) $value)))
            ->duplicates()
            ->first();

        if ($duplicateInRequest) {
            $validator->after(function ($validator) use ($duplicateInRequest) {
                $validator->errors()->add('rows', 'Duplicate Buch No in request: ' . $duplicateInRequest);
            });
        }

        $existingBuchNos = VacuumBuch::where('company_id', $companyId)
            ->whereIn('buch_no', $buchNos->all())
            ->pluck('buch_no')
            ->all();

        if (!empty($existingBuchNos)) {
            $validator->after(function ($validator) use ($existingBuchNos) {
                $validator->errors()->add('rows', 'These Buch No already exist: ' . implode(', ', $existingBuchNos));
            });
        }

        $validated = $validator->validate();
        $now = now();
        $createdRows = collect();

        DB::transaction(function () use ($validated, $companyId, $userId, $now, &$createdRows) {
            foreach ($validated['rows'] as $row) {
                $createdRows->push(VacuumBuch::create([
                    'company_id' => $companyId,
                    'created_by' => $userId,
                    'updated_by' => $userId,
                    'modified_count' => 0,
                    'buch_no' => $row['buch_no'],
                    'size_inch' => $row['size_inch'] ?? null,
                    'weight' => $row['weight'] ?? null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        });

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Buch created successfully',
            'data' => $createdRows
                ->map(fn($row) => $this->formatRow($row->fresh(), $companyId))
                ->values(),
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $data = $this->findForCompany($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Buch not found',
            ], 404);
        }

        $companyId = (int) $request->user()->company_id;
        $validated = $this->validatePayload($request, $companyId, (int) $data->id);

        $data->update([
            'updated_by' => (int) $request->user()->id,
            'modified_count' => ((int) $data->modified_count) + 1,
            'buch_no' => $validated['buch_no'],
            'size_inch' => $validated['size_inch'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Buch updated successfully',
            'data' => $this->formatRow($data->fresh(), $companyId),
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $data = $this->findForCompany($request, (int) $id);

        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Vacuum Buch not found',
            ], 404);
        }

        if ($this->isInUse((int) $request->user()->company_id, (int) $data->id)) {
            return response()->json([
                'success' => false,
                'message' => 'This Buch No is already used in a voucher and cannot be deleted.',
            ], 422);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Buch deleted successfully',
        ]);
    }

    private function validatePayload(Request $request, int $companyId, ?int $id = null): array
    {
        if ($request->has('buch_no')) {
            $request->merge([
                'buch_no' => trim((string) $request->input('buch_no')),
                'size_inch' => $this->nullableNumericInput($request->input('size_inch')),
                'weight' => $this->nullableNumericInput($request->input('weight')),
            ]);
        }

        return $request->validate([
            'buch_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vacuum_buchs', 'buch_no')
                    ->where(fn($query) => $query->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'size_inch' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ], [
            'buch_no.unique' => 'This Buch No already exists.',
            'buch_no.required' => 'Buch No is required.',
        ]);
    }

    private function findForCompany(Request $request, int $id): ?VacuumBuch
    {
        return VacuumBuch::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->first();
    }

    private function isInUse(int $companyId, int $buchId): bool
    {
        return VacuumVoucherItem::where('vacuum_buch_id', $buchId)
            ->whereHas('voucher', fn($query) => $query->where('company_id', $companyId))
            ->exists();
    }

    private function formatRow(VacuumBuch $row, int $companyId): array
    {
        $isUsed = $this->isInUse($companyId, (int) $row->id);

        return [
            'id' => (int) $row->id,
            'company_id' => (int) $row->company_id,
            'buch_no' => $row->buch_no,
            'size_inch' => $row->size_inch !== null ? (string) $row->size_inch : null,
            'weight' => $row->weight !== null ? (string) $row->weight : null,
            'modified_count' => (int) $row->modified_count,
            'created_by' => $row->created_by ? (int) $row->created_by : null,
            'updated_by' => $row->updated_by ? (int) $row->updated_by : null,
            'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
            'updated_at' => optional($row->updated_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($row->created_at)->format('d-m-Y / h:i A'),
            'updated_at_view' => optional($row->updated_at)->format('d-m-Y / h:i A'),
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

    private function nullableNumericInput($value)
    {
        return $value === '' ? null : $value;
    }

    private function requestPayload(Request $request): array
    {
        $payload = $request->all();

        if (!empty($payload)) {
            return $payload;
        }

        $jsonPayload = $request->json()->all();
        if (!empty($jsonPayload)) {
            return $jsonPayload;
        }

        $decoded = json_decode((string) $request->getContent(), true);

        return is_array($decoded) ? $decoded : [];
    }
}
