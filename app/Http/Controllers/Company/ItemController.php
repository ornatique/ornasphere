<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Support\Facades\Crypt;

class ItemController extends Controller
{

    // ================= INDEX =================
    public function index($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.items.index', compact('company'));
    }


    // ================= DATATABLE =================

    public function data($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)
            ->select('items.*'); // important

        return datatables()->of($items)

            ->addIndexColumn() // ✅ THIS FIXES YOUR ERROR

            ->addColumn('status', function ($item) {

                return $item->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-danger">Inactive</span>';
            })

            ->addColumn('action', function ($item) use ($company) {

                $encryptedId = Crypt::encryptString($item->id);

                $editUrl = route(
                    'company.items.edit',
                    [$company->slug, $encryptedId]
                );

                $deleteUrl = route(
                    'company.items.destroy',
                    [$company->slug, $encryptedId]
                );

                return '
                <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                    Edit
                </a>

                <button class="btn btn-sm btn-danger deleteItem"
                    data-url="' . $deleteUrl . '">
                    Delete
                </button>
            ';
            })

            ->rawColumns(['status', 'action'])

            ->make(true);
    }



    // ================= CREATE =================
    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        return view('company.items.create', compact('company'));
    }


    // ================= STORE =================
    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        // ✅ VALIDATION
        $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'required|string|max:255|unique:items,item_code',

            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',

            'outward_carat' => 'nullable|string|max:50',
            'inward_carat' => 'nullable|string|max:50',

            'outward_purity' => 'nullable|string|max:50',
            'inward_purity' => 'nullable|string|max:50',

            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',

            'jobwork_item_type' => 'nullable|string|max:100',

            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',

            'numeric_length' => 'nullable|integer',

            'item_group' => 'nullable|string|max:100',

            'remarks' => 'nullable|string|max:1000',
        ]);

        // ✅ STORE DATA
        Item::create([

            'company_id' => $company->id,

            'item_name' => $request->item_name,
            'item_code' => $request->item_code,

            'metal' => $request->metal,
            'metal_formula' => $request->metal_formula,

            'outward_carat' => $request->outward_carat,
            'inward_carat' => $request->inward_carat,

            'outward_purity' => $request->outward_purity,
            'inward_purity' => $request->inward_purity,

            'labour_type' => $request->labour_type,
            'labour_unit' => $request->labour_unit,

            'jobwork_item_type' => $request->jobwork_item_type,

            'hsn' => $request->hsn,
            'export_hsn' => $request->export_hsn,

            // ✅ CHECKBOX SAFE STORE
            'auto_load_purity' => $request->has('auto_load_purity') ? 1 : 0,
            'auto_create_label_purchase' => $request->has('auto_create_label_purchase') ? 1 : 0,
            'auto_create_label_config' => $request->has('auto_create_label_config') ? 1 : 0,

            'numeric_length' => $request->numeric_length,

            'item_group' => $request->item_group,

            'remarks' => $request->remarks,

            'is_active' => 1,

        ]);

        return redirect()
            ->route('company.items.index', $company->slug)
            ->with('success', 'Item created successfully');
    }



    // ================= EDIT =================
    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        return view('company.items.edit', compact('company', 'item'));
    }



    // ================= UPDATE =================
    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        // VALIDATION
        $request->validate([
            'item_name' => 'required|string|max:255',
            'item_code' => 'required|string|max:255|unique:items,item_code,' . $item->id,

            'metal' => 'nullable|string|max:100',
            'metal_formula' => 'nullable|string|max:100',

            'outward_carat' => 'nullable|string|max:50',
            'inward_carat' => 'nullable|string|max:50',

            'outward_purity' => 'nullable|string|max:50',
            'inward_purity' => 'nullable|string|max:50',

            'labour_type' => 'nullable|string|max:100',
            'labour_unit' => 'nullable|string|max:100',

            'jobwork_item_type' => 'nullable|string|max:100',

            'hsn' => 'nullable|string|max:100',
            'export_hsn' => 'nullable|string|max:100',



            'item_group' => 'nullable|string|max:100',

            'remarks' => 'nullable|string|max:1000',
        ]);

        // UPDATE DATA
        $item->update([

            'item_name' => $request->item_name,
            'item_code' => $request->item_code,

            'metal' => $request->metal,
            'metal_formula' => $request->metal_formula,

            'outward_carat' => $request->outward_carat,
            'inward_carat' => $request->inward_carat,

            'outward_purity' => $request->outward_purity,
            'inward_purity' => $request->inward_purity,

            'labour_type' => $request->labour_type,
            'labour_unit' => $request->labour_unit,

            'jobwork_item_type' => $request->jobwork_item_type,

            'hsn' => $request->hsn,
            'export_hsn' => $request->export_hsn,

            'auto_load_purity' => $request->has('auto_load_purity') ? 1 : 0,
            'auto_create_label_purchase' => $request->has('auto_create_label_purchase') ? 1 : 0,
            'auto_create_label_config' => $request->has('auto_create_label_config') ? 1 : 0,

            'numeric_length' => $request->numeric_length,

            'item_group' => $request->item_group,

            'remarks' => $request->remarks,
        ]);

        return redirect()
            ->route('company.items.index', $company->slug)
            ->with('success', 'Item updated successfully');
    }



    // ================= DELETE =================
    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $itemId = Crypt::decryptString($encryptedId);

        $item = Item::where('id', $itemId)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $item->delete();

        return response()->json([
            'status' => true,
            'message' => 'Item deleted successfully'
        ]);
    }
}
