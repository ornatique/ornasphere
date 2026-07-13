<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\VacuumBuch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class VacuumBuchController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = VacuumBuch::query()
                ->where('company_id', $company->id)
                ->with(['createdByUser:id,name'])
                ->select('vacuum_buchs.*');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('user_name', fn($row) => $row->createdByUser?->name ?? '-')
                ->addColumn('modified_at_view', fn($row) => optional($row->updated_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);
                    $edit = route('company.vacuum-buchs.edit', [$company->slug, $encryptedId]);
                    $delete = route('company.vacuum-buchs.destroy', [$company->slug, $encryptedId]);

                    return '
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . e($delete) . '">Delete</button>
                    ';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.vacuum_buchs.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.vacuum_buchs.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        if ($this->buchNoExists($company->id, (string) $request->input('buch_no'))) {
            return back()
                ->withErrors(['buch_no' => 'This Buch No already exists.'])
                ->with('error', 'This Buch No already exists.')
                ->withInput();
        }

        $validated = $this->validateData($request, $company->id);

        VacuumBuch::create([
            'company_id' => $company->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'modified_count' => 0,
            'buch_no' => $validated['buch_no'],
            'size_inch' => $validated['size_inch'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ]);

        return redirect()
            ->route('company.vacuum-buchs.index', $company->slug)
            ->with('success', 'Vacuum Buch created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumBuch::where('company_id', $company->id)->where('id', $id)->firstOrFail();

        return view('company.vacuum_buchs.create', compact('company', 'data'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumBuch::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        if ($this->buchNoExists($company->id, (string) $request->input('buch_no'), (int) $data->id)) {
            return back()
                ->withErrors(['buch_no' => 'This Buch No already exists.'])
                ->with('error', 'This Buch No already exists.')
                ->withInput();
        }

        $validated = $this->validateData($request, $company->id, (int) $data->id);

        $data->update([
            'updated_by' => auth()->id(),
            'modified_count' => ((int) $data->modified_count) + 1,
            'buch_no' => $validated['buch_no'],
            'size_inch' => $validated['size_inch'] ?? null,
            'weight' => $validated['weight'] ?? null,
        ]);

        return redirect()
            ->route('company.vacuum-buchs.index', $company->slug)
            ->with('success', 'Vacuum Buch updated successfully');
    }

    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $data = VacuumBuch::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Vacuum Buch deleted successfully',
        ]);
    }

    private function validateData(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'buch_no' => [
                'required',
                'string',
                'max:255',
                Rule::unique('vacuum_buchs', 'buch_no')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'size_inch' => ['nullable', 'numeric', 'min:0'],
            'weight' => ['nullable', 'numeric', 'min:0'],
        ]);
    }

    private function buchNoExists(int $companyId, string $buchNo, ?int $ignoreId = null): bool
    {
        $buchNo = trim($buchNo);

        if ($buchNo === '') {
            return false;
        }

        return VacuumBuch::where('company_id', $companyId)
            ->where('buch_no', $buchNo)
            ->when($ignoreId, fn($query) => $query->where('id', '!=', $ignoreId))
            ->exists();
    }
}
