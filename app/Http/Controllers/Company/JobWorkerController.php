<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Models\Company;
use App\Models\JobWorker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Yajra\DataTables\Facades\DataTables;

class JobWorkerController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = JobWorker::where('company_id', $company->id)
                ->select('job_workers.*');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('status', function ($row) {
                    return (int) $row->is_active === 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);
                    $editUrl = route('company.job-workers.edit', [$company->slug, $encryptedId]);
                    $deleteUrl = route('company.job-workers.destroy', [$company->slug, $encryptedId]);

                    return '<a href="' . $editUrl . '" class="btn btn-sm btn-primary">Edit</a>
                            <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($deleteUrl) . '">Delete</button>';
                })
                ->rawColumns(['status', 'action'])
                ->make(true);
        }

        return view('company.job_workers.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        return view('company.job_workers.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $validated = $this->validateJobWorker($request, $company->id);

        JobWorker::create(array_merge($validated, [
            'company_id' => $company->id,
            'is_active' => $request->boolean('is_active', true),
        ]));

        return redirect()
            ->route('company.job-workers.index', $company->slug)
            ->with('success', 'Job Worker created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $jobWorkerId = Crypt::decryptString($encryptedId);

        $jobWorker = JobWorker::where('company_id', $company->id)
            ->where('id', $jobWorkerId)
            ->firstOrFail();

        return view('company.job_workers.edit', compact('company', 'jobWorker'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $jobWorkerId = Crypt::decryptString($encryptedId);

        $jobWorker = JobWorker::where('company_id', $company->id)
            ->where('id', $jobWorkerId)
            ->firstOrFail();

        $validated = $this->validateJobWorker($request, $company->id, $jobWorker->id);

        $jobWorker->update(array_merge($validated, [
            'is_active' => $request->boolean('is_active', false),
        ]));

        return redirect()
            ->route('company.job-workers.index', $company->slug)
            ->with('success', 'Job Worker updated successfully');
    }

    public function destroy(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $jobWorkerId = Crypt::decryptString($encryptedId);

        $jobWorker = JobWorker::where('company_id', $company->id)
            ->where('id', $jobWorkerId)
            ->firstOrFail();

        $jobWorker->delete();

        if ($request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Job Worker deleted successfully',
            ]);
        }

        return redirect()
            ->route('company.job-workers.index', $company->slug)
            ->with('success', 'Job Worker deleted successfully');
    }

    public function exportExcel($slug): StreamedResponse
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = JobWorker::where('company_id', $company->id)->orderBy('name')->get();

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

    public function exportPdf($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $rows = JobWorker::where('company_id', $company->id)->orderBy('name')->get();

        return Pdf::loadView('company.job_workers.pdf.index', compact('company', 'rows'))
            ->setPaper('a4', 'portrait')
            ->download('job_workers_report.pdf');
    }

    private function validateJobWorker(Request $request, int $companyId, ?int $jobWorkerId = null): array
    {
        return $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'nullable',
                'email',
                'max:255',
                Rule::unique('job_workers', 'email')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($jobWorkerId),
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
}
