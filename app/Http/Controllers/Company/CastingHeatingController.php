<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\Company;
use App\Models\JobWorker;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class CastingHeatingController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $fromDate = $request->get('from_date', now()->toDateString());
        $toDate = $request->get('to_date', now()->toDateString());
        $workerId = $request->get('worker_id');

        if ($request->ajax()) {
            $rows = VacuumVoucher::query()
                ->where('company_id', $company->id)
                ->when($fromDate, fn($q) => $q->whereDate('voucher_date', '>=', $fromDate))
                ->when($toDate, fn($q) => $q->whereDate('voucher_date', '<=', $toDate))
                ->when($workerId, fn($q) => $q->where('job_worker_id', $workerId))
                ->with(['process:id,name', 'jobWorker:id,name'])
                ->select('vacuum_vouchers.*')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->withCount('items')
                ->withCount([
                    'heatingItems as in_bhati_count' => fn($q) => $q->where('in_bhati', true),
                ]);

            return DataTables::of($rows)
                ->addIndexColumn()
                ->addColumn('voucher_no_view', fn($row) => $row->voucher_no)
                ->addColumn('date_time_view', fn($row) => optional($row->created_at)->format('d-m-Y / h:i A') ?? '-')
                ->addColumn('process_name', fn($row) => $row->process?->name ?? '-')
                ->addColumn('worker_name', fn($row) => $row->jobWorker?->name ?? '-')
                ->addColumn('total_pcs', fn($row) => (int) ($row->items_count ?? 0))
                ->addColumn('in_bhati_pcs', fn($row) => (int) ($row->in_bhati_count ?? 0))
                ->addColumn('created_at_view', fn($row) => optional($row->created_at)->format('d-m-Y / h:i A') ?? '-')
                ->addColumn('action', function ($row) use ($company) {
                    $id = Crypt::encryptString((string) $row->id);
                    $view = route('company.casting-heating.show', [$company->slug, $id]);

                    return '<a href="' . $view . '" class="btn btn-sm btn-info">View</a>';
                })
                ->rawColumns(['action'])
                ->make(true);
        }

        $jobWorkers = JobWorker::where('company_id', $company->id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name']);

        return view('company.casting_heating.index', compact('company', 'fromDate', 'toDate', 'jobWorkers'));
    }

    public function show(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $heatingItems = CastingHeatingItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $checkedItemIds = $heatingItems
            ->filter(fn($item) => (bool) $item->in_bhati)
            ->keys()
            ->map(fn($id) => (int) $id)
            ->all();
        $inBhatiCount = count($checkedItemIds);

        if ($request->get('download') === 'pdf') {
            return Pdf::loadView('company.casting_heating.pdf.show', compact('company', 'voucher', 'heatingItems', 'inBhatiCount'))
                ->setPaper('a4', 'portrait')
                ->download('casting_heating_' . $voucher->voucher_no . '.pdf');
        }

        return view('company.casting_heating.show', compact('company', 'voucher', 'checkedItemIds', 'heatingItems', 'inBhatiCount'));
    }

    public function pdf($slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);

        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->withCount('items')
            ->findOrFail($id);

        $heatingItems = CastingHeatingItem::where('company_id', $company->id)
            ->where('vacuum_voucher_id', $voucher->id)
            ->get()
            ->keyBy('vacuum_voucher_item_id');

        $inBhatiCount = $heatingItems->filter(fn($item) => (bool) $item->in_bhati)->count();

        return Pdf::loadView('company.casting_heating.pdf.show', compact('company', 'voucher', 'heatingItems', 'inBhatiCount'))
            ->setPaper('a4', 'portrait')
            ->download('casting_heating_' . $voucher->voucher_no . '.pdf');
    }

    public function update(Request $request, $slug, $encryptedId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $id = Crypt::decryptString($encryptedId);
        $voucher = VacuumVoucher::where('company_id', $company->id)->with('items:id,vacuum_voucher_id')->findOrFail($id);
        $validItemIds = $voucher->items->pluck('id')->map(fn($itemId) => (int) $itemId)->all();

        $validated = $request->validate([
            'items' => ['nullable', 'array'],
            'items.*' => [
                'integer',
                Rule::in($validItemIds),
            ],
        ]);

        $checkedIds = collect($validated['items'] ?? [])->map(fn($itemId) => (int) $itemId)->all();

        DB::transaction(function () use ($company, $voucher, $validItemIds, $checkedIds) {
            $existingRows = CastingHeatingItem::where('company_id', $company->id)
                ->where('vacuum_voucher_id', $voucher->id)
                ->whereIn('vacuum_voucher_item_id', $validItemIds)
                ->get()
                ->keyBy('vacuum_voucher_item_id');

            foreach ($validItemIds as $itemId) {
                $isChecked = in_array($itemId, $checkedIds, true);
                $existing = $existingRows->get($itemId);
                $checkedAt = $isChecked
                    ? ($existing?->checked_at ?: now())
                    : null;
                $checkedBy = $isChecked
                    ? ($existing?->checked_by ?: auth()->id())
                    : null;

                CastingHeatingItem::updateOrCreate(
                    [
                        'company_id' => $company->id,
                        'vacuum_voucher_item_id' => $itemId,
                    ],
                    [
                        'vacuum_voucher_id' => $voucher->id,
                        'in_bhati' => $isChecked,
                        'checked_by' => $checkedBy,
                        'checked_at' => $checkedAt,
                    ]
                );
            }
        });

        return redirect()->route('company.casting-heating.index', $company->slug);
    }
}
