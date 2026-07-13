<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingSortingItem;
use App\Models\Company;
use App\Models\Item;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CastingSortingController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());

        if ($request->ajax()) {
            $rows = VacuumVoucher::query()
                ->where('company_id', $company->id)
                ->whereExists(function ($query) use ($company, $fromDate, $toDate) {
                    $query->selectRaw('1')
                        ->from('tree_cutting_receive_items')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                                ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                        })
                        ->when($fromDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at)'), '>=', $fromDate))
                        ->when($toDate, fn($q) => $q->whereDate(DB::raw('COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at)'), '<=', $toDate));
                })
                ->whereNotExists(function ($query) use ($company) {
                    $query->selectRaw('1')
                        ->from('tree_cutting_issue_items')
                        ->whereColumn('tree_cutting_issue_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_issue_items.company_id', $company->id)
                        ->whereNotNull('tree_cutting_issue_items.receive_tree_wt')
                        ->whereNotExists(function ($subQuery) use ($company) {
                            $subQuery->selectRaw('1')
                                ->from('tree_cutting_receive_items')
                                ->whereColumn('tree_cutting_receive_items.tree_cutting_issue_item_id', 'tree_cutting_issue_items.id')
                                ->where('tree_cutting_receive_items.company_id', $company->id)
                                ->where(function ($q) {
                                    $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                                        ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                                });
                        });
                })
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('MAX(COALESCE(tree_cutting_receive_items.received_at, tree_cutting_receive_items.created_at))')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id);
                }, 'tree_receive_datetime')
                ->selectSub(function ($query) use ($company) {
                    $query->from('tree_cutting_receive_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('tree_cutting_receive_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('tree_cutting_receive_items.company_id', $company->id)
                        ->where(function ($q) {
                            $q->whereNotNull('tree_cutting_receive_items.receive_pc_wt')
                                ->orWhereNotNull('tree_cutting_receive_items.receive_tree_bhuko');
                        });
                }, 'tree_receive_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_sorting_items')
                        ->selectRaw('COUNT(*)')
                        ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_sorting_items.company_id', $company->id);
                }, 'sorting_count')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_sorting_items')
                        ->selectRaw('COALESCE(SUM(casting_sorting_items.weight), 0)')
                        ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_sorting_items.company_id', $company->id);
                }, 'sorting_weight_total')
                ->selectSub(function ($query) use ($company) {
                    $query->from('casting_sorting_items')
                        ->selectRaw('COALESCE(SUM(casting_sorting_items.quantity), 0)')
                        ->whereColumn('casting_sorting_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                        ->where('casting_sorting_items.company_id', $company->id);
                }, 'sorting_quantity_total')
                ->orderByDesc('tree_receive_datetime')
                ->orderByDesc('id');

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => $row->tree_receive_datetime ? \Carbon\Carbon::parse($row->tree_receive_datetime)->format('d-m-Y / h:i A') : '-')
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('total_pcs_view', fn($row) => (int) ($row->tree_receive_count ?? 0))
                ->addColumn('sorting_weight_view', fn($row) => number_format((float) ($row->sorting_weight_total ?? 0), 3, '.', ''))
                ->addColumn('sorting_quantity_view', fn($row) => (int) ($row->sorting_quantity_total ?? 0))
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.casting-sorting.show', [$company->slug, $id]);
                    $pdf = \Illuminate\Support\Facades\Route::has('company.casting-sorting.pdf')
                        ? route('company.casting-sorting.pdf', [$company->slug, $id])
                        : url('company/' . $company->slug . '/casting-sorting/' . $id . '/pdf');

                    return '<div class="d-flex gap-1">
                        <a href="' . $view . '" class="btn btn-sm btn-info">View</a>
                        <a href="' . $pdf . '" class="btn btn-sm btn-success">PDF</a>
                    </div>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        return view('company.casting_sorting.index', compact('company', 'fromDate', 'toDate'));
    }

    public function show($slug, $encryptedId)
    {
        [$company, $voucher, $sortingItems, $items, $treeReceiveCount] = $this->voucherData($slug, $encryptedId);

        return view('company.casting_sorting.show', compact('company', 'voucher', 'sortingItems', 'items', 'treeReceiveCount'));
    }

    public function pdf($slug, $encryptedId)
    {
        [$company, $voucher, $sortingItems, $items, $treeReceiveCount] = $this->voucherData($slug, $encryptedId);

        return Pdf::loadView('company.casting_sorting.pdf.show', compact('company', 'voucher', 'sortingItems', 'items', 'treeReceiveCount'))
            ->setPaper('a4', 'portrait')
            ->download('casting_sorting_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        [$company, $voucher] = $this->voucherData($slug, $encryptedId);

        $validated = $request->validate([
            'rows' => ['nullable', 'array'],
            'rows.*.item_id' => [
                'nullable',
                'integer',
                Rule::exists('items', 'id')->where(fn($q) => $q->where('company_id', $company->id)),
            ],
            'rows.*.weight' => ['nullable', 'numeric', 'min:0'],
            'rows.*.quantity' => ['nullable', 'integer', 'min:0'],
        ]);

        DB::transaction(function () use ($company, $voucher, $validated) {
            CastingSortingItem::where('company_id', $company->id)
                ->where('vacuum_voucher_id', $voucher->id)
                ->delete();

            foreach (($validated['rows'] ?? []) as $row) {
                $itemId = $row['item_id'] ?? null;
                $weight = $row['weight'] ?? null;
                $quantity = $row['quantity'] ?? null;

                if (($itemId === null || $itemId === '') && ($weight === null || $weight === '') && ($quantity === null || $quantity === '')) {
                    continue;
                }

                if ($itemId === null || $itemId === '') {
                    continue;
                }

                CastingSortingItem::create([
                    'company_id' => $company->id,
                    'vacuum_voucher_id' => $voucher->id,
                    'item_id' => (int) $itemId,
                    'weight' => $weight !== null && $weight !== '' ? (float) $weight : null,
                    'quantity' => $quantity !== null && $quantity !== '' ? (int) $quantity : null,
                    'sorted_by' => auth()->id(),
                    'sorted_at' => now(),
                ]);
            }
        });

        return redirect()
            ->route('company.casting-sorting.index', $company->slug);
    }

    private function voucherData($slug, $encryptedId): array
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->findOrFail($id);

        $issueCount = TreeCuttingIssueItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->whereNotNull('receive_tree_wt')
            ->count();

        $receiveCount = TreeCuttingReceiveItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->where(function ($q) {
                $q->whereNotNull('receive_pc_wt')
                    ->orWhereNotNull('receive_tree_bhuko');
            })
            ->count();

        abort_if($issueCount === 0 || $issueCount !== $receiveCount, 404);

        $sortingItems = CastingSortingItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('item:id,item_name,item_code')
            ->orderBy('id')
            ->get();

        $items = Item::where('company_id', $company->id)
            ->orderBy('item_name')
            ->get(['id', 'item_name', 'item_code']);

        return [$company, $voucher, $sortingItems, $items, $receiveCount];
    }
}
