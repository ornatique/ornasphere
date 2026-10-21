<?php

namespace App\Services;

use App\Models\Company;
use App\Models\JobworkIssue;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Physical raw stock in net grams; never writes customer fine/cash ledgers. */
class JobworkRawStock
{
    public function lock(int $companyId): void
    {
        Company::whereKey($companyId)->lockForUpdate()->firstOrFail();
    }

    public function consumption(int|array $companyId): Builder
    {
        $issued = DB::table('jobwork_raw_stock_allocations')->whereIn('company_id', (array) $companyId)
            ->selectRaw('jobwork_issue_id, item_id, SUM(net_weight) as issued')->groupBy('jobwork_issue_id', 'item_id');
        $returned = DB::table('jobwork_receive_items as ri')
            ->join('jobwork_receives as r', 'r.id', '=', 'ri.jobwork_receive_id')
            ->whereIn('r.company_id', (array) $companyId)->whereNotNull('r.jobwork_issue_id')
            ->selectRaw('r.jobwork_issue_id, ri.item_id, SUM(ri.receive_net_wt) as returned')
            ->groupBy('r.jobwork_issue_id', 'ri.item_id');

        // A partial return restores each original source receipt proportionally.
        return DB::table('jobwork_raw_stock_allocations as a')
            ->joinSub($issued, 'i', fn($j) => $j->on('i.jobwork_issue_id', '=', 'a.jobwork_issue_id')->on('i.item_id', '=', 'a.item_id'))
            ->leftJoinSub($returned, 'r', fn($j) => $j->on('r.jobwork_issue_id', '=', 'a.jobwork_issue_id')->on('r.item_id', '=', 'a.item_id'))
            ->whereIn('a.company_id', (array) $companyId)
            ->selectRaw('a.voucher_item_id, a.item_id, SUM(a.net_weight * (1 - CASE WHEN COALESCE(r.returned,0) > i.issued THEN i.issued ELSE COALESCE(r.returned,0) END / NULLIF(i.issued,0))) as used_net')
            ->groupBy('a.voucher_item_id', 'a.item_id');
    }

    public function receipts(int $companyId): Builder
    {
        return DB::table('customer_advance_voucher_items as v')
            ->join('customer_advance_vouchers as h', 'h.id', '=', 'v.voucher_id')
            ->join('items as item', 'item.id', '=', 'v.product_id')
            ->leftJoinSub($this->consumption($companyId), 'used', 'used.voucher_item_id', '=', 'v.id')
            ->where('h.company_id', $companyId)->where('item.company_id', $companyId)
            ->whereRaw("LOWER(item.jobwork_item_type) = 'raw'")
            ->selectRaw('v.id, v.product_id as item_id, item.item_name, h.customer_id, h.voucher_date, v.net_weight - COALESCE(used.used_net,0) as available_net');
    }

    public function available(int $companyId): array
    {
        $balances = $this->receipts($companyId)->get()->groupBy('item_id')
            ->map(fn($rows) => round($rows->sum('available_net'), 3))->all();
        foreach ($this->consumption($companyId)->whereNull('a.voucher_item_id')->get() as $debt) {
            $balances[$debt->item_id] = round(($balances[$debt->item_id] ?? 0) - $debt->used_net, 3);
        }
        return $balances;
    }

    public function guardIssueEdit(JobworkIssue $issue): void
    {
        // The existing editor can remove linked receive lines. Keep posted raw returns intact.
        if (DB::table('jobwork_raw_stock_allocations')->where('jobwork_issue_id', $issue->id)->exists()
            && DB::table('jobwork_receive_items as ri')->join('jobwork_receives as r', 'r.id', '=', 'ri.jobwork_receive_id')
                ->where('r.jobwork_issue_id', $issue->id)->exists()) {
            throw ValidationException::withMessages(['items' => 'This raw-material issue has receipts. Remove its receive entries before editing or deleting the issue.']);
        }
    }

    public function guardIssueUpdate(JobworkIssue $issue, array $data): ?array
    {
        if (!DB::table('jobwork_receives')->where('jobwork_issue_id', $issue->id)->exists()) return null;
        foreach (['jobwork_date', 'job_worker_id', 'production_step_id'] as $key) {
            $old = $key === 'jobwork_date' ? substr((string) $issue->$key, 0, 10) : (string) $issue->$key;
            if ($old !== (string) $data[$key]) {
                throw ValidationException::withMessages([$key => 'This issue has receipts. Keep its date, worker and production step unchanged when adding items.']);
            }
        }
        $submitted = collect($data['items'])->keyBy('id');
        $existing = $issue->items()->get();
        foreach ($existing as $line) {
            $row = $submitted->get($line->id);
            if (!$row) throw ValidationException::withMessages(['items' => 'Keep existing issue rows and their IDs. Add additional material as a new row.']);
            foreach (['item_id','gross_wt','other_wt','other_amt','purity','net_purity','net_wt','fine_wt','qty_pcs','total_amt'] as $key) {
                if (abs((float) $line->$key - (float) ($row[$key] ?? 0)) > 0.00001) {
                    throw ValidationException::withMessages(['items' => 'Existing issue quantities cannot change after receipts. Add additional material as a new row.']);
                }
            }
        }
        return $existing->pluck('id')->all();
    }

    public function syncIssue(JobworkIssue $issue, bool $allowShortage = false, ?array $existingIds = null): array
    {
        $shortages = [];
        // Caller holds the company lock in the voucher transaction.
        foreach ($issue->items()->with('item')->get() as $line) {
            if (strtolower((string) $line->item?->jobwork_item_type) !== 'raw') continue;
            $net = (float) $line->net_wt;
            $gross = (float) $line->gross_wt;
            $other = (float) $line->other_wt;
            if ($net <= 0 || $gross < 0 || $other < 0 || ($gross > 0 && abs($gross - $other - $net) > 0.0005)) {
                throw ValidationException::withMessages(['items' => 'Raw material requires positive net weight equal to gross weight minus other weight.']);
            }
        }
        if ($existingIds === null) DB::table('jobwork_raw_stock_allocations')->where('jobwork_issue_id', $issue->id)->delete();
        $rows = $issue->items()->when($existingIds !== null, fn($q) => $q->whereNotIn('jobwork_issue_items.id', $existingIds))->join('items', 'items.id', '=', 'jobwork_issue_items.item_id')
            ->where('items.company_id', $issue->company_id)->whereRaw("LOWER(items.jobwork_item_type) = 'raw'")
            ->selectRaw('items.id as item_id, items.item_name, SUM(jobwork_issue_items.net_wt) as required_net')
            ->groupBy('items.id', 'items.item_name')->get();
        foreach ($rows as $row) {
            $needed = round((float) $row->required_net, 3);
            $lots = $this->receipts((int) $issue->company_id)->where('v.product_id', $row->item_id)
                ->whereDate('h.voucher_date', '<=', $issue->jobwork_date)->orderBy('h.voucher_date')->orderBy('v.id')->get();
            $available = round($lots->sum('available_net'), 3);
            if ($needed > $available + 0.0005) {
                if ($allowShortage) {
                    // Allocate existing stock and record any uncovered weight as stock debt.
                    $shortages[] = ['item_id' => (int) $row->item_id, 'item_name' => $row->item_name,
                        'required_net_wt' => $needed, 'available_net_wt' => max(0, $available),
                        'shortage_net_wt' => round($needed - max(0, $available), 3),
                        'stock_deducted' => true];
                } else {
                throw ValidationException::withMessages(['items' => "$row->item_name: only ".number_format($available, 3, '.', '').' g raw stock is available. Receive/Purchase stock before issuing it.']);
                }
            }
            foreach ($lots as $lot) {
                $take = min($needed, max(0, (float) $lot->available_net));
                if ($take <= 0) continue;
                $allocation = DB::table('jobwork_raw_stock_allocations')
                    ->where('jobwork_issue_id', $issue->id)->where('voucher_item_id', $lot->id);
                if ($existingIds !== null && $allocation->exists()) {
                    $allocation->increment('net_weight', $take);
                } else {
                    DB::table('jobwork_raw_stock_allocations')->insert(['company_id'=>$issue->company_id,
                        'jobwork_issue_id'=>$issue->id, 'voucher_item_id'=>$lot->id, 'item_id'=>$row->item_id, 'net_weight'=>$take]);
                }
                $needed = round($needed - $take, 3);
                if ($needed <= 0) break;
            }
            if ($needed > 0.0005 && $allowShortage) {
                DB::table('jobwork_raw_stock_allocations')->insert([
                    'company_id'=>$issue->company_id, 'jobwork_issue_id'=>$issue->id,
                    'voucher_item_id'=>null, 'item_id'=>$row->item_id, 'net_weight'=>$needed,
                ]);
            }
        }
        return $shortages;
    }

    public function validateReturns(int $companyId, ?int $issueId = null, bool $enforceIssuedLimit = true): void
    {
        $issued = DB::table('jobwork_issue_items')->selectRaw('jobwork_issue_id, item_id, SUM(net_wt) as issued')
            ->groupBy('jobwork_issue_id', 'item_id');
        $returns = DB::table('jobwork_receive_items as ri')->join('jobwork_receives as r', 'r.id', '=', 'ri.jobwork_receive_id')
            ->join('items as item', 'item.id', '=', 'ri.item_id')
            ->leftJoinSub($issued, 'i', fn($j) => $j->on('i.jobwork_issue_id', '=', 'r.jobwork_issue_id')->on('i.item_id', '=', 'ri.item_id'))
            ->where('r.company_id', $companyId)->whereNotNull('r.jobwork_issue_id')->whereRaw("LOWER(item.jobwork_item_type) = 'raw'")
            ->when($issueId !== null, fn($q) => $q->where('r.jobwork_issue_id', $issueId))
            ->selectRaw('r.jobwork_issue_id, ri.item_id, item.item_name, COALESCE(i.issued,0) as issued, SUM(ri.receive_net_wt) as returned')
            ->groupBy('r.jobwork_issue_id', 'ri.item_id', 'item.item_name', 'i.issued')->get();
        foreach ($returns as $row) {
            if ($enforceIssuedLimit && $row->returned > $row->issued + 0.0005) {
                throw ValidationException::withMessages(['items' => "$row->item_name: raw return cannot exceed the weight issued on this voucher."]);
            }
        }
        // Reducing/deleting a return must not take back stock already re-issued.
        $receipts = $this->receipts($companyId);
        if ($issueId !== null) {
            $receipts->whereIn('v.id', DB::table('jobwork_raw_stock_allocations')
                ->where('company_id', $companyId)->where('jobwork_issue_id', $issueId)->select('voucher_item_id'));
        }
        if ($receipts->whereRaw('v.net_weight - COALESCE(used.used_net,0) < -0.0005')->exists()) {
            throw ValidationException::withMessages(['items' => 'This raw stock has already been issued again. Reverse that issue before reducing the return.']);
        }
    }

    public function debtReport(int|array $companyId, bool $api = false, bool $includeCompany = false): Builder
    {
        $query = DB::query()->fromSub($this->consumption($companyId)->whereNull('a.voucher_item_id'), 'debt')
            ->join('items', 'items.id', '=', 'debt.item_id')
            ->whereIn('items.company_id', (array) $companyId)->whereRaw('debt.used_net > 0.0005')
            ->selectRaw("debt.item_id, items.item_name, 'raw_stock_debt' as stock_type, 'Raw Stock Shortage' as stock_type_name, NULL as customer_id, '-' as customer_name, 0 as qty_pcs, -debt.used_net as gross_weight, 0 as ".($api ? 'less_weight' : 'other_weight').", -debt.used_net as net_weight, 0 as fine_weight, 0 as labour_amount, 0 as other_amount");
        if ($includeCompany) $query->selectRaw('items.company_id');
        if ($api) $query->selectRaw('NULL as created_at');
        return $query;
    }

    /** Join onto report receipt rows, preserving non-raw receipt behaviour. */
    public function joinReport(Builder $query, int $companyId): void
    {
        $query->leftJoinSub($this->consumption($companyId), 'raw_used', 'raw_used.voucher_item_id', '=', 'cavi.id');
    }

    public static function reportWeight(string $column): string
    {
        return "COALESCE(cavi.$column,0) * CASE WHEN COALESCE(cavi.net_weight,0) > 0 THEN (cavi.net_weight - COALESCE(raw_used.used_net,0)) / cavi.net_weight ELSE 1 END";
    }
}
