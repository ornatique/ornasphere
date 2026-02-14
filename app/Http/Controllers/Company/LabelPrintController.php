<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\Item;
use App\Models\LabelConfig;
use App\Models\ItemLabel;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Barryvdh\DomPDF\Facade\Pdf;
use Yajra\DataTables\Facades\DataTables;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class LabelPrintController extends Controller
{

    /*
    =====================================================
    INDEX PAGE
    =====================================================
    */

    public function index(Request $request, $slug)
    {

        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = ItemLabel::with('item')
                ->where('company_id', $company->id)
                ->select('item_labels.*')
                ->orderBy('serial_no', 'asc');

            return DataTables::of($data)

                ->addIndexColumn()

                ->addColumn('item_name', function ($row) {
                    return $row->item->item_name ?? '';
                })

                ->addColumn('serial', function ($row) {
                    return $row->qr_code;
                })

                ->addColumn('qr_code', function ($row) use ($company) {

                    $qrUrl = route(
                        'company.label.print.qr',
                        [$company->slug, $row->qr_code]
                    );

                    return '<img src="' . $qrUrl . '" width="60">';
                })

                // ✅ THIS FIXES SEARCH
                ->filterColumn('serial', function ($query, $keyword) {

                    $query->where('item_labels.qr_code', 'like', "%{$keyword}%");
                })

                ->rawColumns(['qr_code'])

                ->make(true);
        }

        $items = Item::where(
            'company_id',
            $company->id
        )->get();

        return view(
            'company.label_print.index',
            compact('company', 'items')
        );
    }




    /*
    =====================================================
    GENERATE LABELS
    =====================================================
    */

    public function generate(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $request->validate([
            'item_id' => 'required',
            'qty' => 'required|integer|min:1|max:500'
        ]);


        $labelConfig = LabelConfig::where(

            'item_id',
            $request->item_id

        )->where(

            'company_id',
            $company->id

        )->firstOrFail();


        /*
        COMPANY CODE
        example:
        test-jewellary-255 → TJ
        */

        $words = explode('-', $company->slug);

        $companyCode = '';

        foreach ($words as $word) {

            if (!is_numeric($word)) {

                $companyCode .= strtoupper(substr($word, 0, 1));
            }
        }


        /*
        GET LAST SERIAL FROM item_labels
        */

        $lastSerial = ItemLabel::where(

            'label_config_id',
            $labelConfig->id

        )->max('serial_no');


        $start = $lastSerial ? $lastSerial + 1 : 1;


        /*
        GENERATE LABELS
        */

        for ($i = 0; $i < $request->qty; $i++) {

            $serial = $start + $i;

            $serialFormatted = str_pad(

                $serial,

                $labelConfig->numeric_length,

                '0',

                STR_PAD_LEFT

            );


            $finalCode =
                $labelConfig->prefix .
                $serialFormatted;


            ItemLabel::create([

                'company_id' => $company->id,

                'item_id' => $request->item_id,

                'label_config_id' => $labelConfig->id,

                'qr_code' => $finalCode,

                'barcode' => $finalCode,

                'serial_no' => $serial

            ]);
        }


        return redirect()

            ->route('company.label.print', $company->slug)

            ->with('success', 'Labels generated successfully');
    }



    /*
    =====================================================
    PRINT PDF
    =====================================================
    */

    public function printPDF($slug, $itemId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $labels = ItemLabel::where(

            'company_id',
            $company->id

        )->where(

            'item_id',
            $itemId

        )->get();


        $pdf = Pdf::loadView(

            'company.label_print.pdf',

            compact('labels')

        )->setPaper('A4');


        return $pdf->download('labels.pdf');
    }


    public function qrImage($slug, $qr_code)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $label = ItemLabel::where('company_id', $company->id)
            ->where('qr_code', $qr_code)
            ->firstOrFail();

        $qr = new QrCode(
            data: $label->qr_code,
            size: 120,
            margin: 5
        );

        $writer = new PngWriter();

        $result = $writer->write($qr);

        return new Response(
            $result->getString(),
            200,
            ['Content-Type' => 'image/png']
        );
    }
}
