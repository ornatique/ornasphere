<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\LabourFormula;
use App\Models\ProductionCost;
use App\Models\ProductionStep;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class ProductionStepController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {
            $rows = ProductionStep::query()
                ->where('company_id', $company->id)
                ->select('production_steps.*')
                ->selectSub(function ($q) use ($company) {
                    $q->from('jobwork_issues')
                        ->selectRaw('COUNT(DISTINCT job_worker_id)')
                        ->whereColumn('jobwork_issues.production_step_id', 'production_steps.id')
                        ->where('jobwork_issues.company_id', $company->id);
                }, 'assigned_jobworkers_count')
                ->with([
                    'labourFormula:id,name',
                    'productionCost:id,name',
                    'createdByUser:id,name',
                    'updatedByUser:id,name',
                ]);

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('labour_formula_name', fn($row) => $row->labourFormula?->name ?? '-')
                ->addColumn('production_cost_name', fn($row) => $row->productionCost?->name ?? '-')
                 ->addColumn('user_name', fn($row) => $row->createdByUser?->name ?? $row->updatedByUser?->name ?? '-')
                ->addColumn('assigned_users_count', fn($row) => (int) ($row->assigned_jobworkers_count ?? 0))
                ->addColumn('receivable_loss_badge', function ($row) {
                    return (int) $row->receivable_loss === 1
                        ? '<span class="badge bg-success">Yes</span>'
                        : '<span class="badge bg-secondary">No</span>';
                })
                ->addColumn('modified_at_view', fn($row) => optional($row->updated_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $encryptedId = Crypt::encryptString((string) $row->id);
                    $edit = route('company.production-step.edit', [$company->slug, $encryptedId]);
                    $delete = route('company.production-step.destroy', [$company->slug, $encryptedId]);

                    return '
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>
                        <button type="button" class="btn btn-sm btn-danger deleteBtn" data-url="' . $delete . '">Delete</button>
                    ';
                })
                ->rawColumns(['receivable_loss_badge', 'action'])
                ->make(true);
        }

        return view('company.production_step.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $labourFormulas = LabourFormula::where('company_id', $company->id)->where('status', true)->orderBy('name')->get();
        $productionCosts = ProductionCost::where('company_id', $company->id)->where('status', true)->orderBy('name')->get();

        return view('company.production_step.create', compact('company', 'labourFormulas', 'productionCosts'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $validated = $this->validateData($request, $company->id);

        $productionStep = ProductionStep::create([
            'company_id' => $company->id,
            'created_by' => auth()->id(),
            'updated_by' => auth()->id(),
            'modified_count' => 0,
            'name' => $validated['name'],
            'labour_formula_id' => $validated['labour_formula_id'] ?? null,
            'receivable_loss' => (bool) ($validated['receivable_loss'] ?? false),
            'auto_create_cost' => (bool) ($validated['auto_create_cost'] ?? false),
            'production_cost_id' => $validated['production_cost_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        return redirect()
            ->route('company.production-step.index', $company->slug)
            ->with('success', 'Production Step created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = ProductionStep::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        $labourFormulas = LabourFormula::where('company_id', $company->id)->where('status', true)->orderBy('name')->get();
        $productionCosts = ProductionCost::where('company_id', $company->id)->where('status', true)->orderBy('name')->get();

        return view('company.production_step.create', compact('company', 'data', 'labourFormulas', 'productionCosts'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = ProductionStep::where('company_id', $company->id)->where('id', $id)->firstOrFail();
        $validated = $this->validateData($request, $company->id, (int) $data->id);

        $data->update([
            'name' => $validated['name'],
            'updated_by' => auth()->id(),
            'modified_count' => ((int) $data->modified_count) + 1,
            'labour_formula_id' => $validated['labour_formula_id'] ?? null,
            'receivable_loss' => (bool) ($validated['receivable_loss'] ?? false),
            'auto_create_cost' => (bool) ($validated['auto_create_cost'] ?? false),
            'production_cost_id' => $validated['production_cost_id'] ?? null,
            'remarks' => $validated['remarks'] ?? null,
            'status' => (bool) ($validated['status'] ?? true),
        ]);

        return redirect()
            ->route('company.production-step.index', $company->slug)
            ->with('success', 'Production Step updated successfully');
    }

    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $data = ProductionStep::where('company_id', $company->id)->where('id', $id)->firstOrFail();
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
            'receivable_loss' => ['nullable', 'boolean'],
            'auto_create_cost' => ['nullable', 'boolean'],
            'production_cost_id' => [
                'nullable',
                'integer',
                Rule::exists('production_costs', 'id')->where(fn($q) => $q->where('company_id', $companyId)),
            ],
            'remarks' => ['nullable', 'string'],
            'status' => ['required', 'boolean'],
        ]);
    }
}
