<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\OtherCharge;
use App\Models\Item;
use App\Models\User;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class OtherChargeController extends Controller
{
    private function boolLike($value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        if ($value === null || $value === '') {
            return false;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1;
        }

        $v = strtolower(trim((string) $value));
        return in_array($v, ['1', 'true', 'yes', 'on', 'y'], true);
    }

    private function isCompanyAdminUser(User $user): bool
    {
        if (strtolower((string) $user->role) === 'company_admin') {
            return true;
        }

        return $user->hasRole('company_admin');
    }

    private function canOtherChargeAction(?User $authUser, string $action): bool
    {
        if (!$authUser) {
            return false;
        }

        if ($this->isCompanyAdminUser($authUser)) {
            return true;
        }

        $moduleVariants = [
            'other-charge',
            'othercharge',
            'other_charge',
            'other.charge',
            'other charge',
        ];

        $candidates = [];
        foreach ($moduleVariants as $module) {
            $candidates[] = "{$module}-{$action}";
            $candidates[] = "{$module}.{$action}";
            $candidates[] = "{$module}_{$action}";
            $candidates[] = "{$module} {$action}";
            $candidates[] = "{$action}-{$module}";
            $candidates[] = "{$action}.{$module}";
            $candidates[] = "{$action}_{$module}";
            $candidates[] = "{$action} {$module}";
        }

        if ($action !== 'view') {
            foreach ($moduleVariants as $module) {
                $candidates[] = "{$module}-manage";
                $candidates[] = "{$module}.manage";
                $candidates[] = "{$module}_manage";
                $candidates[] = "{$module} manage";
                $candidates[] = "manage-{$module}";
                $candidates[] = "manage.{$module}";
                $candidates[] = "manage_{$module}";
                $candidates[] = "manage {$module}";
            }
        }

        return $authUser->hasAnyPermission(array_values(array_unique($candidates)));
    }

    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $authUser = $request->user();
        $canCreate = $this->canOtherChargeAction($authUser, 'create');
        $canEdit = $this->canOtherChargeAction($authUser, 'edit');
        $canDelete = $this->canOtherChargeAction($authUser, 'delete');

        if ($request->ajax()) {

            $data = OtherCharge::where('company_id', $company->id);

            return DataTables::of($data)

                ->addIndexColumn()

                ->addColumn('action', function ($row) use ($company, $canEdit, $canDelete) {

                    $id = Crypt::encryptString($row->id);

                    $edit = route('company.other-charge.edit', [$company->slug, $id]);

                    $delete = route('company.other-charge.destroy', [$company->slug, $id]);
                    $usageCount = $this->otherChargeUsageCount((int) $company->id, (int) $row->id);
                    $html = '';

                    if ($canEdit) {
                        $html .= '<a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a> ';
                    }

                    if ($canDelete) {
                        $html .= $usageCount > 0
                            ? '<span class="badge bg-danger">In Use</span>'
                            : '<button type="button"
                                class="btn btn-sm btn-danger deleteBtn"
                                data-url="' . $delete . '">
                                Delete
                            </button>';
                    }

                    return $html !== '' ? $html : '-';
                })


                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.other_charge.index', compact('company', 'canCreate'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)->get();

        return view('company.other_charge.create', compact('company', 'items'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $data = $request->all();

        $data['company_id'] = $company->id;

        OtherCharge::create($data);

        return redirect()
            ->route('company.other-charge.index', $slug)
            ->with('success', 'Created successfully');
    }

    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $data = OtherCharge::findOrFail($id);

        $items = Item::where('company_id', $company->id)->get();

        return view('company.other_charge.create', compact('company', 'data', 'items'));
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $otherCharge = OtherCharge::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $otherCharge->update([

            'other_charge' => $request->other_charge,
            'code' => $request->code,
            'default_amount' => $request->default_amount,
            'default_weight' => $request->default_weight,
            'quantity_pcs' => $request->quantity_pcs,
            'weight_formula' => $request->weight_formula,
            'weight_percent' => $request->weight_percent,
            'sale_weight_percent' => $request->sale_weight_percent,
            'purchase_weight_percent' => $request->purchase_weight_percent,
            'sequence_no' => $request->sequence_no,
            'item_id' => $request->item_id,
            'remarks' => $request->remarks,

            'is_default' => $this->boolLike($request->input('is_default')),
            'is_selected' => $this->boolLike($request->input('is_selected')),
            'diamond' => $this->boolLike($request->input('diamond')),
            'stone' => $this->boolLike($request->input('stone')),
            'stock_effect' => $this->boolLike($request->input('stock_effect')),
            'other_amt_formula' => $request->other_amt_formula,
            'other_charge_ol' => $this->boolLike($request->other_charge_ol),
            'purity' => $request->purity,
            'required_purity' => $request->required_purity,
            'merge_other_charge' => $request->merge_other_charge,
            'wt_operation' => $request->wt_operation,
            'carat_weight_auto_conversion' => $this->boolLike($request->carat_weight_auto_conversion),
            'party_account_effect' => $this->boolLike($request->party_account_effect),
        ]);

        return redirect()
            ->route('company.other-charge.index', $slug)
            ->with('success', 'Other Charge Updated Successfully');
    }
    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $data = OtherCharge::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        if ($this->otherChargeUsageCount((int) $company->id, (int) $data->id) > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Other Charge is used in transactions and cannot be deleted.',
            ], 422);
        }

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Other Charge deleted successfully'
        ]);
    }

    private function otherChargeUsageCount(int $companyId, int $otherChargeId): int
    {
        $jobworkIssueCount = (int) DB::table('jobwork_issue_items as jii')
            ->join('jobwork_issues as ji', 'ji.id', '=', 'jii.jobwork_issue_id')
            ->where('ji.company_id', $companyId)
            ->where('jii.other_charge_id', $otherChargeId)
            ->count();

        $chargeIdNeedles = [
            '"charge_id":' . $otherChargeId . ',',
            '"charge_id":' . $otherChargeId . '}',
            '"charge_id": ' . $otherChargeId . ',',
            '"charge_id": ' . $otherChargeId . '}',
        ];

        $jobworkReceiveCount = (int) DB::table('jobwork_receive_items as jri')
            ->join('jobwork_receives as jr', 'jr.id', '=', 'jri.jobwork_receive_id')
            ->where('jr.company_id', $companyId)
            ->where(function ($query) use ($chargeIdNeedles) {
                foreach ($chargeIdNeedles as $needle) {
                    $query->orWhere('jri.other_charge_details', 'like', '%' . $needle . '%');
                }
            })
            ->count();

        $saleItemCount = 0;
        if (Schema::hasColumn('sale_items', 'other_charge_details')) {
            $saleItemCount = (int) DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->where('s.company_id', $companyId)
                ->where(function ($query) use ($chargeIdNeedles) {
                    foreach ($chargeIdNeedles as $needle) {
                        $query->orWhere('si.other_charge_details', 'like', '%' . $needle . '%');
                    }
                })
                ->count();
        }

        $approvalItemCount = 0;
        if (Schema::hasColumn('approval_items', 'other_charge_details')) {
            $approvalItemCount = (int) DB::table('approval_items as ai')
                ->join('approval_headers as ah', 'ah.id', '=', 'ai.approval_id')
                ->where('ah.company_id', $companyId)
                ->where(function ($query) use ($chargeIdNeedles) {
                    foreach ($chargeIdNeedles as $needle) {
                        $query->orWhere('ai.other_charge_details', 'like', '%' . $needle . '%');
                    }
                })
                ->count();
        }

        return $jobworkIssueCount + $jobworkReceiveCount + $saleItemCount + $approvalItemCount;
    }

    public function options(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $query = OtherCharge::query()
            ->where('company_id', $company->id)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id');

        $rows = $query->get();

        return response()->json($rows->map(function ($row) {
            return [
                'id' => $row->id,
                'name' => $row->other_charge,
                'code' => $row->code,
                'default_amount' => (float) ($row->default_amount ?? 0),
                'default_weight' => (float) ($row->default_weight ?? 0),
                'quantity_pcs' => (float) ($row->quantity_pcs ?? 1),
                'weight_formula' => $row->weight_formula,
                'weight_percent' => (float) ($row->weight_percent ?? 0),
                'wt_operation' => $row->wt_operation ?: 'less',
                'stock_effect' => (bool) $row->stock_effect,
                'other_amt_formula' => $row->other_amt_formula,
                'is_default' => (bool) $row->is_default,
                'is_selected' => (bool) $row->is_selected,
                'item_id' => $row->item_id,
            ];
        }));
    }
}
