<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LabourFormula;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class LabourFormulaController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = LabourFormula::where('company_id', $company->id)
                ->select('labour_formulas.*');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('status_badge', function ($row) {
                    return (int) $row->status === 1
                        ? '<span class="badge bg-success">Active</span>'
                        : '<span class="badge bg-danger">Inactive</span>';
                })
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);

                    $edit = route('company.labour-formula.edit', [$company->slug, $encryptedId]);
                    $delete = route('company.labour-formula.destroy', [$company->slug, $encryptedId]);

                    return '
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . $delete . '">Delete</button>
                    ';
                })
                ->rawColumns(['status_badge', 'action'])
                ->make(true);
        }

        return view('company.labour_formula.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        return view('company.labour_formula.create', compact('company'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $validated = $this->validateData($request, $company->id);

        LabourFormula::create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        return redirect()
            ->route('company.labour-formula.index', $company->slug)
            ->with('success', 'Labour Formula created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = LabourFormula::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        return view('company.labour_formula.create', compact('company', 'data'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = LabourFormula::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $validated = $this->validateData($request, $company->id, (int) $data->id);

        $data->update([
            'name' => $validated['name'],
            'status' => (bool) ($validated['status'] ?? false),
        ]);

        return redirect()
            ->route('company.labour-formula.index', $company->slug)
            ->with('success', 'Labour Formula updated successfully');
    }

    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = LabourFormula::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($this->isUsedInJobwork((int) $company->id, (int) $data->id, (string) $data->name)) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete: this Labour Formula is already used in Jobwork Issue.',
            ], 422);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Labour Formula deleted successfully',
        ]);
    }

    private function validateData(Request $request, int $companyId, ?int $id = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('labour_formulas', 'name')
                    ->where(fn($q) => $q->where('company_id', $companyId))
                    ->ignore($id),
            ],
            'status' => ['required', 'boolean'],
        ]);
    }

    private function isUsedInJobwork(int $companyId, int $labourFormulaId, string $labourFormulaName): bool
    {
        $checks = [
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
            } else {
                $query->where($column, $labourFormulaName);
            }

            if ($query->exists()) {
                return true;
            }
        }

        return false;
    }
}

