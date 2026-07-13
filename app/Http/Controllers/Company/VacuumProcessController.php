<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\VacuumProcess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class VacuumProcessController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = VacuumProcess::query()
                ->where('company_id', $company->id)
                ->with(['createdByUser:id,name'])
                ->select('vacuum_processes.*');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('user_name', fn($row) => $row->createdByUser?->name ?? '-')
                ->addColumn('modified_at_view', fn($row) => optional($row->updated_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);
                    $edit = route('company.vacuum-processes.edit', [$company->slug, $encryptedId]);
                    $delete = route('company.vacuum-processes.destroy', [$company->slug, $encryptedId]);

                    return '
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($delete) . '">Delete</button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.vacuum_processes.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.vacuum_processes.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $validated = $this->validateData($request, $company->id);

        VacuumProcess::create([
            'company_id' => $company->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'modified_count' => 0,
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('company.vacuum-processes.index', $company->slug)
            ->with('success', 'Vacuum Process created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumProcess::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        return view('company.vacuum_processes.create', compact('company', 'data'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumProcess::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        $validated = $this->validateData($request, $company->id, (int) $data->id);

        $data->update([
            'updated_by' => auth()->id(),
            'modified_count' => ((int) $data->modified_count) + 1,
            'name' => $validated['name'],
        ]);

        return redirect()
            ->route('company.vacuum-processes.index', $company->slug)
            ->with('success', 'Vacuum Process updated successfully');
    }

    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumProcess::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Process deleted successfully',
        ]);
    }

    private function validateData(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vacuum_processes', 'name')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
        ]);
    }
}
