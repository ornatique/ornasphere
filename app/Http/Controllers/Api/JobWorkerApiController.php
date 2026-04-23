<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\JobWorker;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JobWorkerApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;

        $rows = $this->queryRows($request, $companyId)
            ->latest()
            ->get();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $row = JobWorker::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Job Worker not found.'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $row,
        ]);
    }

    public function exportExcel(Request $request): StreamedResponse
    {
        $companyId = (int) $request->user()->company_id;
        $rows = $this->queryRows($request, $companyId)
            ->orderBy('name')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Name', 'Email', 'Mobile', 'City', 'Area', 'Landmark', 'Status']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->name,
                    $r->email,
                    $r->mobile_no,
                    $r->city,
                    $r->area,
                    $r->landmark,
                    (int) $r->is_active === 1 ? 'Active' : 'Inactive',
                ]);
            }
            fclose($out);
        }, 'job_workers_report.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $company = Company::select('id', 'name')->findOrFail($companyId);
        $rows = $this->queryRows($request, $companyId)
            ->orderBy('name')
            ->get();

        return Pdf::loadView('company.job_workers.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('job_workers_report.pdf');
    }

    public function store(Request $request)
    {
        $companyId = $request->user()->company_id;
        $validated = $this->validatePayload($request, $companyId);

        $row = JobWorker::create(array_merge($validated, [
            'company_id' => $companyId,
            'is_active' => $request->boolean('is_active', true) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Job Worker created successfully.',
            'data' => $row,
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $companyId = $request->user()->company_id;

        $row = JobWorker::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Job Worker not found.'
            ], 404);
        }

        $validated = $this->validatePayload($request, $companyId, $row->id);

        $row->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', (bool) $row->is_active) ? 1 : 0,
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Job Worker updated successfully.',
            'data' => $row,
        ]);
    }

    public function destroy(Request $request, $id)
    {
        $companyId = $request->user()->company_id;
        $row = JobWorker::where('company_id', $companyId)
            ->where('id', $id)
            ->first();

        if (!$row) {
            return response()->json([
                'success' => false,
                'message' => 'Job Worker not found.'
            ], 404);
        }

        $row->delete();

        return response()->json([
            'success' => true,
            'message' => 'Job Worker deleted successfully.',
        ]);
    }

    private function validatePayload(Request $request, int $companyId, ?int $id = null): array
    {
        $nameRule = $id ? 'sometimes' : 'required';

        return $request->validate([
            'name' => [$nameRule, 'string', 'max:255'],
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('job_workers', 'email')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'mobile_no' => 'nullable|string|max:20',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:191',
            'area' => 'nullable|string|max:191',
            'landmark' => 'nullable|string|max:191',
            'pincode' => 'nullable|string|max:20',
            'contact_person1_name' => 'nullable|string|max:191',
            'contact_person1_phone' => 'nullable|string|max:20',
            'contact_person2_name' => 'nullable|string|max:191',
            'contact_person2_phone' => 'nullable|string|max:20',
            'gst_no' => 'nullable|string|max:191',
            'pan_no' => 'nullable|string|max:191',
            'aadhaar_no' => 'nullable|string|max:191',
            'birth_date' => 'nullable|date',
            'anniversary_date' => 'nullable|date',
            'reference' => 'nullable|string|max:191',
            'remarks' => 'nullable|string',
        ]);
    }

    private function queryRows(Request $request, int $companyId)
    {
        return JobWorker::where('company_id', $companyId)
            ->when($request->filled('search'), function ($q) use ($request) {
                $search = trim((string) $request->search);
                $q->where(function ($q2) use ($search) {
                    $q2->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile_no', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('city', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('is_active'), function ($q) use ($request) {
                $q->where('is_active', (int) $request->is_active);
            });
    }
}
