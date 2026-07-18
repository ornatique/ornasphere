<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\Company;
use App\Models\VacuumVoucher;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CastingHeatingApiController extends Controller
{
    public function index(Request $request)
    {
        $companyId = (int) $request->user()->company_id;
        $fromDate = $request->input('from_date', now()->toDateString());
        $toDate = $request->input('to_date', now()->toDateString());

        $rows = VacuumVoucher::query()
            ->where('company_id', $companyId)
            ->when($fromDate, fn($query) => $query->whereDate('voucher_date', '>=', $fromDate))
            ->when($toDate, fn($query) => $query->whereDate('voucher_date', '<=', $toDate))
            ->when($request->filled('worker_id'), fn($query) => $query->where('job_worker_id', (int) $request->input('worker_id')))
            ->when($request->filled('process_id'), fn($query) => $query->where('vacuum_process_id', (int) $request->input('process_id')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = trim((string) $request->input('search'));
                $query->where(function ($q) use ($search) {
                    $q->where('voucher_no', 'like', "%{$search}%")
                        ->orWhereHas('process', fn($process) => $process->where('name', 'like', "%{$search}%"))
                        ->orWhereHas('jobWorker', fn($worker) => $worker->where('name', 'like', "%{$search}%"));
                });
            })
            ->with(['process:id,name', 'jobWorker:id,name'])
            ->select('vacuum_vouchers.*')
            ->selectSub(function ($query) use ($companyId) {
                $query->from('casting_heating_items')
                    ->selectRaw('MAX(COALESCE(casting_heating_items.checked_at, casting_heating_items.created_at))')
                    ->whereColumn('casting_heating_items.vacuum_voucher_id', 'vacuum_vouchers.id')
                    ->where('casting_heating_items.company_id', $companyId);
            }, 'heating_datetime')
            ->withCount('items')
            ->withCount([
                'heatingItems as in_bhati_count' => fn($query) => $query->where('in_bhati', true),
            ])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn($row) => [
                'id' => (int) $row->id,
                'voucher_no' => $row->voucher_no,
                'voucher_date' => optional($row->voucher_date)->format('Y-m-d'),
                'date_time' => $row->heating_datetime ? \Carbon\Carbon::parse($row->heating_datetime)->format('d-m-Y / h:i A') : optional($row->created_at)->format('d-m-Y / h:i A'),
                'process_datetime' => $row->heating_datetime ? \Carbon\Carbon::parse($row->heating_datetime)->format('Y-m-d H:i:s') : optional($row->created_at)->format('Y-m-d H:i:s'),
                'process_id' => (int) $row->vacuum_process_id,
                'process_name' => $row->process?->name,
                'worker_id' => (int) $row->job_worker_id,
                'worker_name' => $row->jobWorker?->name,
                'total_pcs' => (int) ($row->items_count ?? 0),
                'in_bhati_pcs' => (int) ($row->in_bhati_count ?? 0),
                'created_at' => optional($row->created_at)->format('Y-m-d H:i:s'),
                'created_at_view' => optional($row->created_at)->format('d-m-Y / h:i A'),
            ])
            ->values();

        return response()->json([
            'success' => true,
            'data' => $rows,
        ]);
    }

    public function show(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Heating voucher not found',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatVoucher($voucher),
        ]);
    }

    public function update(Request $request, $id)
    {
        $voucher = VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->with('items:id,vacuum_voucher_id')
            ->find((int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Heating voucher not found',
            ], 404);
        }

        $validItemIds = $voucher->items->pluck('id')->map(fn($itemId) => (int) $itemId)->all();
        $checkedIds = collect($request->input('checked_item_ids', $request->input('items', [])))
            ->map(fn($itemId) => (int) $itemId)
            ->values()
            ->all();

        validator(['items' => $checkedIds], [
            'items' => ['nullable', 'array'],
            'items.*' => ['integer', Rule::in($validItemIds)],
        ])->validate();

        DB::transaction(function () use ($request, $voucher, $validItemIds, $checkedIds) {
            $existingRows = CastingHeatingItem::where('company_id', (int) $request->user()->company_id)
                ->where('vacuum_voucher_id', $voucher->id)
                ->whereIn('vacuum_voucher_item_id', $validItemIds)
                ->get()
                ->keyBy('vacuum_voucher_item_id');

            foreach ($validItemIds as $itemId) {
                $isChecked = in_array($itemId, $checkedIds, true);
                $existing = $existingRows->get($itemId);
                $checkedAt = $isChecked ? ($existing?->checked_at ?: now()) : null;
                $checkedBy = $isChecked ? ($existing?->checked_by ?: (int) $request->user()->id) : null;

                CastingHeatingItem::updateOrCreate(
                    [
                        'company_id' => (int) $request->user()->company_id,
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

        return response()->json([
            'success' => true,
            'message' => 'Casting Heating updated successfully',
            'data' => $this->formatVoucher($this->findVoucher($request, (int) $voucher->id)),
        ]);
    }

    public function pdf(Request $request, $id)
    {
        $voucher = $this->findVoucher($request, (int) $id);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Casting Heating voucher not found',
            ], 404);
        }

        $company = Company::findOrFail((int) $request->user()->company_id);
        $heatingItems = $voucher->heatingItems->keyBy('vacuum_voucher_item_id');
        $inBhatiCount = $heatingItems->filter(fn($item) => (bool) $item->in_bhati)->count();

        return Pdf::loadView('company.casting_heating.pdf.show', compact('company', 'voucher', 'heatingItems', 'inBhatiCount'))
            ->setPaper('a4', 'portrait')
            ->download('casting_heating_' . $voucher->voucher_no . '.pdf');
    }

    private function findVoucher(Request $request, int $id): ?VacuumVoucher
    {
        return VacuumVoucher::where('company_id', (int) $request->user()->company_id)
            ->where('id', $id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items', 'heatingItems'])
            ->withCount('items')
            ->first();
    }

    private function formatVoucher(VacuumVoucher $voucher): array
    {
        $heatingItems = $voucher->heatingItems->keyBy('vacuum_voucher_item_id');
        $inBhatiCount = $heatingItems->filter(fn($item) => (bool) $item->in_bhati)->count();
        $processDateTime = $this->latestProcessDateTime($voucher->heatingItems, 'checked_at') ?: $voucher->created_at;

        return [
            'id' => (int) $voucher->id,
            'voucher_no' => $voucher->voucher_no,
            'voucher_date' => optional($voucher->voucher_date)->format('Y-m-d'),
            'date_time' => optional($processDateTime)->format('d-m-Y / h:i A'),
            'process_datetime' => optional($processDateTime)->format('Y-m-d H:i:s'),
            'process_id' => (int) $voucher->vacuum_process_id,
            'process_name' => $voucher->process?->name,
            'worker_id' => (int) $voucher->job_worker_id,
            'worker_name' => $voucher->jobWorker?->name,
            'total_pcs' => (int) ($voucher->items_count ?? $voucher->items->count()),
            'in_bhati_pcs' => $inBhatiCount,
            'created_at' => optional($voucher->created_at)->format('Y-m-d H:i:s'),
            'created_at_view' => optional($voucher->created_at)->format('d-m-Y / h:i A'),
            'items' => $voucher->items->map(function ($item) use ($heatingItems) {
                $heatingItem = $heatingItems->get($item->id);

                return [
                    'id' => (int) $item->id,
                    'vacuum_voucher_item_id' => (int) $item->id,
                    'vacuum_buch_id' => (int) $item->vacuum_buch_id,
                    'buch_no' => $item->buch_no,
                    'gross_wt' => (float) $item->gross_wt,
                    'buch_wt' => (float) $item->buch_wt,
                    'net_wt' => (float) $item->net_wt,
                    'silver_wt' => (float) $item->silver_wt,
                    'in_bhati' => (bool) ($heatingItem?->in_bhati ?? false),
                    'checked_by' => $heatingItem?->checked_by ? (int) $heatingItem->checked_by : null,
                    'checked_at' => optional($heatingItem?->checked_at)->format('Y-m-d H:i:s'),
                    'checked_at_view' => optional($heatingItem?->checked_at)->format('d-m-Y / h:i A'),
                ];
            })->values(),
        ];
    }

    private function latestProcessDateTime($rows, string $preferredColumn)
    {
        return $rows
            ->map(fn($row) => $row->{$preferredColumn} ?: $row->created_at)
            ->filter()
            ->sortDesc()
            ->first();
    }
}
