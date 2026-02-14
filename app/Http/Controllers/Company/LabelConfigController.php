<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\LabelConfig;
use App\Models\Company;
use App\Models\Item;
use Illuminate\Support\Facades\Crypt;
use Yajra\DataTables\Facades\DataTables;

class LabelConfigController extends Controller
{

    // ================= INDEX =================
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = LabelConfig::with('item')
                ->where('company_id', $company->id)
                ->latest();

            return DataTables::of($data)
                 ->addIndexColumn()
                ->addColumn('item_name', function ($row) {
                    return $row->item->item_name ?? '';
                })

                ->addColumn('action', function ($row) use ($company) {

                    $encryptedId = Crypt::encryptString($row->id);

                    $editUrl = route(
                        'company.label_config.edit',
                        [$company->slug, $encryptedId]
                    );

                    $deleteUrl = route(
                        'company.label_config.delete',
                        [$company->slug, $encryptedId]
                    );

                    return '
                    <a href="' . $editUrl . '" class="btn btn-sm btn-primary">
                        Edit
                    </a>

                    <form action="' . $deleteUrl . '" method="POST"
                        style="display:inline-block; margin-left:5px;">

                        ' . csrf_field() . '
                        ' . method_field("DELETE") . '

                        <button type="submit"
                            class="btn btn-sm btn-danger"
                            onclick="return confirm(\'Are you sure?\')">
                            Delete
                        </button>

                    </form>
                ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view(
            'company.label_config.index',
            compact('company')
        );
    }


    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get();

        return view('company.label_config.create', compact('company', 'items'));
    }
    // ================= STORE =================
    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'item_id' => 'required|exists:items,id',
        ]);

        LabelConfig::updateOrCreate(

            [
                'company_id' => $company->id,
                'item_id' => $request->item_id
            ],

            [
                'prefix' => $request->prefix,
                'numeric_length' => $request->numeric_length,
                'last_no' => $request->last_no ?? 0,
                'reuse' => $request->reuse ? 1 : 0,
                'random' => $request->random ? 1 : 0,
                'min_no' => $request->min_no,
                'max_no' => $request->max_no,
                'from_date' => $request->from_date,
                'to_date' => $request->to_date,
            ]

        );

        return response()->json([
            'status' => true,
            'message' => 'Label Config Saved Successfully'
        ]);
    }


    // ================= EDIT =================
    public function edit($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $items = Item::where('company_id', $company->id)->get();

        return view(
            'company.label_config.edit',
            compact('company', 'labelConfig', 'items')
        );
    }



    // ================= UPDATE =================
    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $request->validate([
            'item_id' => 'required|exists:items,id',
            'numeric_length' => 'nullable|integer',
            'min_no' => 'nullable|integer',
            'max_no' => 'nullable|integer',
        ]);

        $labelConfig->update([

            'item_id' => $request->item_id,
            'prefix' => $request->prefix,
            'numeric_length' => $request->numeric_length,
            'last_no' => $request->last_no ?? 0,
            'reuse' => $request->reuse ? 1 : 0,
            'random' => $request->random ? 1 : 0,
            'min_no' => $request->min_no,
            'max_no' => $request->max_no,
            'from_date' => $request->from_date,
            'to_date' => $request->to_date,

        ]);

        return redirect()
            ->route('company.label_config.index', $company->slug)
            ->with('success', 'Label Config Updated Successfully');
    }



    // ================= DELETE =================
    public function destroy($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $labelConfig = LabelConfig::where('id', $id)
            ->where('company_id', $company->id)
            ->firstOrFail();

        $labelConfig->delete();

        return redirect()
            ->route('company.label_config.index', $company->slug)
            ->with('success', 'Label Config Deleted Successfully');
    }
}
