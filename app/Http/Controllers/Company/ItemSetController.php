<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSet;

class ItemSetController extends Controller
{

    public function index($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $items = Item::where('company_id', $company->id)->get();

        return view(
            'company.item_sets.index',
            compact('company', 'items')
        );
    }


    /*
    LOAD MORE ROWS ON SCROLL
    */

    public function loadMore(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $offset = $request->offset;

        $sets = ItemSet::where('company_id', $company->id)
            ->offset($offset)
            ->limit(10)
            ->get();

        return response()->json($sets);
    }


    /*
    AUTO SAVE CELL
    */

    public function saveCell(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'item_id' => 'required',
            'column'  => 'required',
            'value'   => 'nullable'
        ]);

        $id = $request->id;

        /////////////////////////////////////////////////////
        // IF ID EXISTS → UPDATE
        /////////////////////////////////////////////////////

        if ($id) {

            $itemSet = ItemSet::where('id', $id)
                ->where('company_id', $company->id)
                ->first();

            if ($itemSet) {

                $itemSet->{$request->column} = $request->value;

                $itemSet->save();

                return response()->json([
                    'status' => true,
                    'id' => $itemSet->id
                ]);
            }
        }

        /////////////////////////////////////////////////////
        // ELSE CREATE NEW ROW
        /////////////////////////////////////////////////////

        $itemSet = ItemSet::create([

            'company_id' => $company->id,

            'item_id' => $request->item_id,

            $request->column => $request->value

        ]);

        return response()->json([
            'status' => true,
            'id' => $itemSet->id
        ]);
    }




    public function getItemDetails($slug, $itemId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $item = Item::where('company_id', $company->id)
            ->where('id', $itemId)
            ->first();

        if (!$item) {
            return response()->json([
                'status' => false
            ]);
        }

        return response()->json([
            'status' => true,
            'carat' => $item->outward_carat,
            'purity' => $item->outward_purity,
        ]);
    }
}
