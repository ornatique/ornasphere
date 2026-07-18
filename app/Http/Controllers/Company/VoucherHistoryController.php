<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\CastingHeatingItem;
use App\Models\CastingMetalIssueItem;
use App\Models\CastingReleaseItem;
use App\Models\CastingSortingItem;
use App\Models\Company;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumVoucher;
use Illuminate\Http\Request;

class VoucherHistoryController extends Controller
{
    public function index(Request $request, $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $selectedVoucherId = $request->integer('voucher_id') ?: null;

        $vouchers = VacuumVoucher::where('company_id', $company->id)
            ->latest('id')
            ->limit(300)
            ->get(['id', 'voucher_no', 'voucher_date']);

        $voucher = null;
        $history = null;

        if ($selectedVoucherId) {
            $voucher = VacuumVoucher::where('company_id', $company->id)
                ->with(['process:id,name', 'jobWorker:id,name', 'items'])
                ->find($selectedVoucherId);

            if ($voucher) {
                $history = $this->historyForVoucher($company->id, $voucher);
            }
        }

        return view('company.voucher_history.index', compact('company', 'vouchers', 'voucher', 'history', 'selectedVoucherId'));
    }

    public function data($slug, $voucherId)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $voucher = VacuumVoucher::where('company_id', $company->id)
            ->with(['process:id,name', 'jobWorker:id,name', 'items'])
            ->find((int) $voucherId);

        if (!$voucher) {
            return response()->json([
                'success' => false,
                'message' => 'Voucher not found.',
            ], 404);
        }

        $history = $this->historyForVoucher($company->id, $voucher);

        return response()->json([
            'success' => true,
            'voucher' => [
                'date' => optional($voucher->voucher_date)->format('d-m-Y'),
                'process' => $voucher->process?->name ?? '',
                'worker' => $voucher->jobWorker?->name ?? '',
            ],
            'html' => view('company.voucher_history.partials.history', compact('voucher', 'history'))->render(),
        ]);
    }

    private function historyForVoucher(int $companyId, VacuumVoucher $voucher): array
    {
        $heatingItems = CastingHeatingItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $metalIssueItems = CastingMetalIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $releaseItems = CastingReleaseItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('voucherItem:id,buch_no')
            ->orderBy('id')
            ->get();

        $treeIssueItems = TreeCuttingIssueItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get();

        $treeReceiveItems = TreeCuttingReceiveItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with(['voucherItem:id,buch_no', 'jobWorker:id,name'])
            ->orderBy('is_custom')
            ->orderBy('id')
            ->get();

        $sortingItems = CastingSortingItem::where('company_id', $companyId)
            ->where('vacuum_voucher_id', $voucher->id)
            ->with('item:id,item_name,item_code')
            ->orderBy('id')
            ->get();

        return [
            'summary' => [
                'total_pcs' => $voucher->items->count(),
                'gross_wt' => $this->decimal($voucher->gross_wt_total),
                'buch_wt' => $this->decimal($voucher->buch_wt_total),
                'net_wt' => $this->decimal($voucher->net_wt_total),
                'silver_wt' => $this->decimal($voucher->silver_wt_total),
            ],
            'casting_heating' => [
                'in_bhati_count' => $heatingItems->where('in_bhati', true)->count(),
                'total_count' => $heatingItems->count(),
                'rows' => $heatingItems->map(fn($item) => [
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'in_bhati' => $item->in_bhati ? 'Yes' : 'No',
                    'checked_at' => $this->dateTime($item->checked_at ?: $item->created_at),
                ]),
            ],
            'casting_metal_issue' => [
                'rows' => $metalIssueItems->map(fn($item) => [
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'silver_wt' => $this->decimal($item->issue_silver_wt),
                    'issued_at' => $this->dateTime($item->issued_at ?: $item->created_at),
                ]),
            ],
            'casting_receive' => [
                'rows' => $releaseItems->map(fn($item) => [
                    'buch_no' => $item->voucherItem?->buch_no ?? '-',
                    'release_tree_wt' => $this->decimal($item->release_tree_wt),
                    'release_tree_bhuko' => $this->decimal($item->release_tree_bhuko),
                    'loss' => $this->decimal($item->loss),
                    'received_at' => $this->dateTime($item->released_at ?: $item->created_at),
                ]),
            ],
            'tree_cutting_issue' => [
                'rows' => $treeIssueItems->map(fn($item) => [
                    'buch_no' => $this->buchNo($item),
                    'worker' => $item->jobWorker?->name ?? '-',
                    'receive_tree_wt' => $this->decimal($item->receive_tree_wt),
                    'issued_at' => $this->dateTime($item->issued_at ?: $item->created_at),
                ]),
            ],
            'tree_cutting_receive' => [
                'rows' => $treeReceiveItems->map(fn($item) => [
                    'buch_no' => $this->buchNo($item),
                    'worker' => $item->jobWorker?->name ?? '-',
                    'receive_pc_wt' => $this->decimal($item->receive_pc_wt),
                    'receive_tree_bhuko' => $this->decimal($item->receive_tree_bhuko),
                    'loss' => $this->decimal($item->loss),
                    'received_at' => $this->dateTime($item->received_at ?: $item->created_at),
                ]),
            ],
            'casting_sorting' => [
                'rows' => $sortingItems->map(fn($item) => [
                    'item' => trim(($item->item?->item_name ?? '-') . ($item->item?->item_code ? ' - ' . $item->item->item_code : '')),
                    'weight' => $this->decimal($item->weight),
                    'quantity' => $item->quantity !== null ? (int) $item->quantity : '-',
                    'sorted_at' => $this->dateTime($item->sorted_at ?: $item->created_at),
                ]),
            ],
        ];
    }

    private function buchNo($item): string
    {
        if ($item->is_custom && $item->custom_buch_no) {
            return $item->custom_buch_no;
        }

        return $item->voucherItem?->buch_no ?? '-';
    }

    private function decimal($value): string
    {
        return number_format((float) ($value ?? 0), 3, '.', '');
    }

    private function dateTime($value): string
    {
        return $value ? \Carbon\Carbon::parse($value)->format('d-m-Y h:i A') : '-';
    }
}
