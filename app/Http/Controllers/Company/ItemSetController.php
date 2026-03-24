<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Item;
use App\Models\ItemSet;
use App\Models\LabelConfig;
use Illuminate\Support\Facades\DB;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\Builder\Builder;



class ItemSetController extends Controller
{

    public function list_data(Request $request, $slug)
    {

        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = ItemSet::with('item')
                ->where('company_id', $company->id)
                ->latest();

            // ✅ DATE FILTER
            if ($request->from_date && $request->to_date) {
                $data->whereDate('created_at', '>=', $request->from_date)
                    ->whereDate('created_at', '<=', $request->to_date);
            }

            return datatables()->of($data)
                ->addIndexColumn()

                ->addColumn('item_name', function ($row) {
                    return $row->item->item_name ?? '-';
                })

                ->addColumn('gross_weight', fn($row) => $row->gross_weight)
                ->addColumn('net_weight', fn($row) => $row->net_weight)
                ->addColumn('qr_code', fn($row) => $row->qr_code)

                ->addColumn('date', function ($row) {
                    return $row->created_at
                        ? $row->created_at->format('d-m-Y')
                        : '-';
                })
                ->filterColumn('item_name', function ($query, $keyword) {
                    $query->whereHas('item', function ($q) use ($keyword) {
                        $q->where('item_name', 'like', "%{$keyword}%");
                    });
                })

                ->filterColumn('qr_code', function ($query, $keyword) {
                    $query->where('qr_code', 'like', "%{$keyword}%");
                })

                ->addColumn('action', function ($row) use ($company) {
                    $editUrl = route('company.itemsets.edit', [$company->slug, $row->id]);

                   
                    $deleteUrl = route('company.itemsets.delete', [$company->slug, $row->id]);

                    return '
                     <button class="btn btn-sm btn-primary editBtn"
                            data-url="' . $editUrl . '">
                            Edit
                        </button>
                        <button class="btn btn-sm btn-danger deleteBtn"
                            data-url="' . $deleteUrl . '">
                            Delete
                        </button>
                    ';
                })

                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.item_sets.list', compact('company'));
    }

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

        return ItemSet::where('company_id', $company->id)
            ->where('item_id', $request->item_id)
            ->where('is_final', 0)
            ->orderBy('id')
            ->offset($request->offset)
            ->limit(10)
            ->get();
    }


    /*
    AUTO SAVE CELL
    */

    public function saveCell(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        // prevent blank save
        if (trim($request->value) == "") {
            return response()->json([
                'id' => $request->id
            ]);
        }

        if ($request->id) {
            $set = ItemSet::where('company_id', $company->id)
                ->where('id', $request->id)
                ->where('is_final', 0)
                ->first();

            if ($set) {
                $set->{$request->column} = $request->value;
                $set->save();

                return response()->json(['id' => $set->id]);
            }
        }

        // create new draft
        $set = ItemSet::create([

            'company_id' => $company->id,
            'item_id' => $request->item_id,
            $request->column => $request->value,
            'is_final' => 0

        ]);

        return response()->json([
            'id' => $set->id
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


    public function finalize(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $item = Item::where('company_id', $company->id)
            ->where('id', $request->item_id)
            ->firstOrFail();

        $config = LabelConfig::where('company_id', $company->id)
            ->where('item_id', $item->id)
            ->firstOrFail();

        DB::beginTransaction();

        try {

            // ONLY VALID ROWS
            $draftSets = ItemSet::where('company_id', $company->id)
                ->where('item_id', $item->id)
                ->where('is_final', 0)
                ->where(function ($q) {
                    $q->whereNotNull('gross_weight')
                        ->orWhereNotNull('net_weight')
                        ->orWhereNotNull('size')
                        ->orWhereNotNull('HUID');
                })
                ->orderBy('id')
                ->get();

            if ($draftSets->isEmpty()) {
                return response()->json([
                    'status' => false,
                    'message' => 'No valid item sets to finalize'
                ]);
            }

            $nextNo = $config->last_no;

            foreach ($draftSets as $set) {

                $nextNo++;

                $serialFormatted = str_pad(
                    $nextNo,
                    $config->numeric_length,
                    '0',
                    STR_PAD_LEFT
                );

                $qrText = $config->prefix . $serialFormatted;

                $set->update([
                    'serial_no' => $nextNo,
                    'qr_code'   => $qrText,
                    'barcode'   => $qrText,
                    'is_final'  => 1
                ]);
            }

            $config->update([
                'last_no' => $nextNo
            ]);

            DB::commit();

            return response()->json([
                'status' => true,
                'message' => 'QR generated successfully'
            ]);
        } catch (\Exception $e) {

            DB::rollback();

            return response()->json([
                'status' => false,
                'message' => $e->getMessage()
            ]);
        }
    }

    public function qrList($slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $itemSets = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_final', 1)
            ->whereNotNull('qr_code')
            ->latest()
            ->get();

        return view(
            'company.item_sets.qr_list',
            compact('company', 'itemSets')
        );
    }

    public function generateQrImage($slug, $id)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $set = ItemSet::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $qr = new QrCode($set->qr_code);

        $writer = new PngWriter();

        $result = $writer->write($qr);

        return response($result->getString())
            ->header('Content-Type', 'image/png');
    }


    public function printPdf(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $ids = explode(',', $request->ids);

        $itemSets = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->get();

        $writer = new PngWriter();

        foreach ($itemSets as $set) {

            $qrCode = new QrCode($set->qr_code);

            $result = $writer->write($qrCode);

            $set->qr_base64 =
                'data:image/png;base64,' .
                base64_encode($result->getString());
        }

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView(
            'company.item_sets.print_pdf',
            compact('itemSets')
        );

        return $pdf->stream('qr-labels.pdf');
    }

    public function edit($slug, $id)
    {
        $item = ItemSet::findOrFail($id);

        return response()->json($item);
    }

    public function update(Request $request, $slug, $id)
    {
        $item = ItemSet::findOrFail($id);

        $item->update([
            'gross_weight' => $request->gross_weight,
            'net_weight' => $request->net_weight,
            'size' => $request->size,
            'other' => $request->other,
            'HUID' => $request->huid,
        ]);

        return response()->json(['success' => true]);
    }

    public function destroy($slug, $id)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $item = ItemSet::where('company_id', $company->id)
            ->where('id', $id)
            ->firstOrFail();

        $item->delete();

        return response()->json(['success' => true]);
    }
}
