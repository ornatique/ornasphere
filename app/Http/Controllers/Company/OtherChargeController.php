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

            $data = OtherCharge::where('company_id',$company->id);

            return DataTables::of($data)

                ->addIndexColumn()

                ->addColumn('action', function ($row) use ($company) {

                    $id = Crypt::encryptString($row->id);

                    $edit = route('company.other-charge.edit', [$company->slug,$id]);

                    return "
                        <a href='$edit' class='btn btn-sm btn-primary'>Edit</a>
                    ";
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.other_charge.index', compact('company'));
    }

    public function create($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id',$company->id)->get();

        return view('company.other_charge.create', compact('company','items'));
    }

    public function store(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $data = $request->all();

        $data['company_id'] = $company->id;

        OtherCharge::create($data);

        return redirect()
            ->route('company.other-charge.index',$slug)
            ->with('success','Created successfully');
    }

    public function edit($slug,$encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $id = Crypt::decryptString($encryptedId);

        $data = OtherCharge::findOrFail($id);

        $items = Item::where('company_id',$company->id)->get();

        return view('company.other_charge.edit',compact('company','data','items'));
    }

    public function update(Request $request,$slug,$encryptedId)
    {
        $id = Crypt::decryptString($encryptedId);

        $data = OtherCharge::findOrFail($id);

        $data->update($request->all());

        return redirect()
            ->route('company.other-charge.index',$slug)
            ->with('success','Updated successfully');
    }

}

