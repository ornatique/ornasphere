<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\OtherCharge;
use App\Models\Item;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;

class OtherChargeController extends Controller
{

    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = OtherCharge::where('company_id', $company->id);

            return DataTables::of($data)

                ->addIndexColumn()

                ->addColumn('action', function ($row) use ($company) {

                    $id = Crypt::encryptString($row->id);

                    $edit = route('company.other-charge.edit', [$company->slug, $id]);

                    $delete = route('company.other-charge.destroy', [$company->slug, $id]);

                    return '
                        <a href="' . $edit . '" class="btn btn-sm btn-primary">Edit</a>

                        <button type="button"
                            class="btn btn-sm btn-danger deleteBtn"
                            data-url="' . $delete . '">
                            Delete
                        </button>
                    ';
                })


                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.other_charge.index', compact('company'));
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

        // FIX HERE
        $id = Crypt::decrypt($encryptedId);

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

            'is_default' => $request->has('is_default'),
            'is_selected' => $request->has('is_selected'),
            'diamond' => $request->has('diamond'),
            'stone' => $request->has('stone'),
            'stock_effect' => $request->has('stock_effect'),
            'other_amt_formula' => $request->other_amt_formula,
            'other_charge_ol' => $request->other_charge_ol,
            'purity' => $request->purity,
            'required_purity' => $request->required_purity,
            'merge_other_charge' => $request->merge_other_charge,
            'wt_operation' => $request->wt_operation,
            'carat_weight_auto_conversion' => $request->carat_weight_auto_conversion,
            'party_account_effect' => $request->party_account_effect,
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

        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Other Charge deleted successfully'
        ]);
    }

    public function options(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $itemId = (int) $request->input('item_id', 0);

        $query = OtherCharge::query()
            ->where('company_id', $company->id)
            ->orderByRaw('COALESCE(sequence_no, 999999) asc')
            ->orderBy('id');

        if ($itemId > 0) {
            $query->where(function ($q) use ($itemId) {
                $q->whereNull('item_id')
                    ->orWhere('item_id', 0)
                    ->orWhere('item_id', $itemId);
            });
        }

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
                'other_amt_formula' => $row->other_amt_formula,
                'is_default' => (bool) $row->is_default,
                'is_selected' => (bool) $row->is_selected,
                'item_id' => $row->item_id,
            ];
        }));
    }
}
