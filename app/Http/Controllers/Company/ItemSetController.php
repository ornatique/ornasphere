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
use Yajra\DataTables\Facades\DataTables;
use Carbon\Carbon;



class ItemSetController extends Controller
{
    private function labourFormulaLabel(?string $labourType): string
    {
        return match (strtolower((string) $labourType)) {
            'per_netweight' => 'Per Net Weight',
            'per_fineweight' => 'Per Fine Weight',
            'per_grossweight' => 'Per Gross Weight',
            'per_quantity' => 'Per Quantity',
            'flat' => 'Flat',
            default => 'Per Net Weight',
        };
    }

    public function list_data(Request $request, $slug)
    {

        $company = Company::whereSlug($slug)->firstOrFail();

        if ($request->ajax()) {

            $data = ItemSet::with('item')
                ->where('company_id', $company->id)
                ->where('is_final', 1)
                ->whereNotNull('qr_code')
                ->latest();

            // ✅ DATE FILTER (default: today)
            $fromDate = $request->input('from_date');
            $toDate = $request->input('to_date');

            if (empty($fromDate) && empty($toDate)) {
                $fromDate = Carbon::today()->toDateString();
                $toDate = Carbon::today()->toDateString();
            } elseif (!empty($fromDate) && empty($toDate)) {
                $toDate = $fromDate;
            } elseif (empty($fromDate) && !empty($toDate)) {
                $fromDate = $toDate;
            }

            if (!empty($fromDate) && !empty($toDate)) {
                $data->whereDate('created_at', '>=', $fromDate)
                    ->whereDate('created_at', '<=', $toDate);
            }

            // ✅ ITEM FILTER
            if ($request->filled('item_id')) {
                $data->where('item_id', (int) $request->item_id);
            }

            return datatables()->of($data)
                ->addIndexColumn()

                ->addColumn('item_name', function ($row) {
                    return $row->item->item_name ?? '-';
                })

                ->addColumn('gross_weight', fn($row) => $row->gross_weight)
                ->addColumn('other_weight', fn($row) => $row->other)
                ->addColumn('net_weight', fn($row) => $row->net_weight)
                ->addColumn('qr_code', fn($row) => $row->qr_code)
                ->addColumn('qty_pcs', fn($row) => 1)
                ->addColumn('printed_at', function ($row) {
                    return $row->printed_at
                        ? $row->printed_at->format('d-m-Y h:i A')
                        : '';
                })

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
                    $printUrl = route('company.item_sets.printPdf', $company->slug) . '?ids=' . $row->id;

                    return '
                     <a class="btn btn-sm btn-success"
                            href="' . $printUrl . '"
                            target="_blank">
                            Print
                        </a>
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

        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get();

        return view('company.item_sets.list', compact('company', 'items'));
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
            ->where(function ($q) {
                $q->whereNotNull('gross_weight')
                    ->orWhereNotNull('net_weight')
                    ->orWhereNotNull('sale_labour_rate')
                    ->orWhereNotNull('sale_labour_amount')
                    ->orWhereNotNull('sale_other')
                    ->orWhereNotNull('size')
                    ->orWhereNotNull('HUID');
            })
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
        $toNumber = function ($value): float {
            if ($value === null || $value === '') {
                return 0;
            }
            return (float) str_replace(',', '', (string) $value);
        };

        $item = Item::where('company_id', $company->id)
            ->where('id', $request->item_id)
            ->first();
        $defaultLabourFormula = $this->labourFormulaLabel(optional($item)->labour_type);

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

                // Keep net_weight always in sync with gross_weight and other.
                if (in_array($request->column, ['gross_weight', 'other'], true)) {
                    $gross = $toNumber($request->column === 'gross_weight' ? $request->value : $set->gross_weight);
                    $other = $toNumber($request->column === 'other' ? $request->value : $set->other);
                    $set->net_weight = max(0, $gross - $other);
                }
                if (empty($set->sale_labour_formula)) {
                    $set->sale_labour_formula = $defaultLabourFormula;
                }

                $set->save();

                return response()->json(['id' => $set->id]);
            }
        }

        $payload = [
            'company_id' => $company->id,
            'item_id' => $request->item_id,
            $request->column => $request->value,
            'sale_labour_formula' => $defaultLabourFormula,
            'is_final' => 0
        ];

        // On first cell save also store computed net_weight if gross/other entered.
        if (in_array($request->column, ['gross_weight', 'other'], true)) {
            $gross = $toNumber($request->column === 'gross_weight' ? $request->value : 0);
            $other = $toNumber($request->column === 'other' ? $request->value : 0);
            $payload['net_weight'] = max(0, $gross - $other);
        }

        // create new draft
        $set = ItemSet::create($payload);

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
            'sale_labour_formula' => $this->labourFormulaLabel($item->labour_type),
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
            ->first();

        if (!$config) {
            return response()->json([
                'status' => false,
                'message' => 'Label Config not found for selected item. Please create Label Config first.',
            ], 422);
        }

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

                $qrText = $config->prefix . $nextNo;

                // Fallback safety: if net_weight is empty, compute from gross-other.
                $gross = (float) ($set->gross_weight ?? 0);
                $other = (float) ($set->other ?? 0);
                $net = ($set->net_weight === null || $set->net_weight === '')
                    ? max(0, $gross - $other)
                    : (float) $set->net_weight;

                $set->update([
                    'serial_no' => $nextNo,
                    'qr_code'   => $qrText,
                    'barcode'   => $qrText,
                    'net_weight' => $net,
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

    public function finalizeGet($slug)
    {
        $request = request();

        if ($request->filled('item_id')) {
            return $this->finalize($request, $slug);
        }

        return redirect()->route('company.item_sets.index', $slug);
    }

    public function qrList(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();

        $fromDate = $request->input('from_date');
        $toDate = $request->input('to_date');
        $today = Carbon::today()->toDateString();

        if (empty($fromDate) && empty($toDate)) {
            $fromDate = $today;
            $toDate = $today;
        } elseif (!empty($fromDate) && empty($toDate)) {
            $toDate = $fromDate;
        } elseif (empty($fromDate) && !empty($toDate)) {
            $fromDate = $toDate;
        }

        $query = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->where('is_final', 1)
            ->whereNotNull('qr_code')
            ->orderByRaw('CASE WHEN printed_at IS NULL THEN 0 ELSE 1 END ASC')
            ->orderByDesc(DB::raw('COALESCE(printed_at, created_at)'));

        if ($request->filled('item_id')) {
            $query->where('item_id', (int) $request->item_id);
        }

        if (!empty($fromDate) && !empty($toDate)) {
            $query->whereDate(DB::raw('COALESCE(printed_at, created_at)'), '>=', $fromDate)
                  ->whereDate(DB::raw('COALESCE(printed_at, created_at)'), '<=', $toDate);
        }

        if ($request->ajax() && $request->boolean('only_ids')) {
            return response()->json([
                'ids' => $query->pluck('id')->map(fn($id) => (string) $id)->values(),
            ]);
        }

        if ($request->ajax()) {
            return DataTables::of($query)
                ->addColumn('select', function ($row) {
                    $isUnprinted = (int) ($row->is_printed ?? 0) === 0;
                    $checked = $isUnprinted ? 'checked' : '';
                    $defaultChecked = $isUnprinted ? '1' : '0';

                    return '<input type="checkbox" class="qrCheckbox" value="' . $row->id . '" ' . $checked . ' data-default-checked="' . $defaultChecked . '">';
                })
                ->addColumn('item_name', function ($row) {
                    return optional($row->item)->item_name ?? '-';
                })
                ->addColumn('label_code', function ($row) {
                    return $row->qr_code ?? '-';
                })
                ->addColumn('gross_weight', function ($row) {
                    return number_format((float) $row->gross_weight, 3);
                })
                ->addColumn('other_weight', function ($row) {
                    return number_format((float) $row->other, 3);
                })
                ->addColumn('net_weight', function ($row) {
                    return number_format((float) $row->net_weight, 3);
                })
                ->addColumn('sale_other', function ($row) {
                    return number_format((float) $row->sale_other, 2);
                })
                ->addColumn('date_time', function ($row) {
                    if ($row->printed_at) {
                        return $row->printed_at->format('d-m-Y h:i A');
                    }

                    return '';
                })
                ->filterColumn('item_name', function ($q, $keyword) {
                    $q->whereHas('item', function ($itemQ) use ($keyword) {
                        $itemQ->where('item_name', 'like', "%{$keyword}%");
                    });
                })
                ->rawColumns(['select'])
                ->make(true);
        }

        $items = Item::where('company_id', $company->id)->orderBy('item_name')->get();

        return view(
            'company.item_sets.qr_list',
            compact('company', 'items', 'fromDate', 'toDate')
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

        $idsParam = $request->input('ids', []);
        if (is_array($idsParam)) {
            $ids = array_values(array_filter($idsParam, function ($id) {
                return $id !== null && $id !== '';
            }));
        } else {
            $ids = array_values(array_filter(explode(',', (string) $idsParam)));
        }

        $ids = array_map('intval', $ids);
        $ids = array_values(array_unique(array_filter($ids, fn($id) => $id > 0)));

        if (empty($ids)) {
            return back()->with('error', 'Please select at least one label.');
        }

        $itemSets = ItemSet::with('item')
            ->where('company_id', $company->id)
            ->whereIn('id', $ids)
            ->where('is_final', 1)
            ->get();

        ItemSet::where('company_id', $company->id)
            ->whereIn('id', $itemSets->pluck('id'))
            ->whereNull('printed_at')
            ->update([
                'is_printed' => 1,
                'printed_at' => now(),
            ]);

        $itemSets->each(function ($set) {
            $set->is_printed = 1;
            // Keep original first printed date on reprint.
            $set->printed_at = $set->printed_at ?? now();
        });

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
        )->setPaper([0, 0, 311.81, 550.11]);

        return $pdf->stream('label-print-preview.pdf');
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

