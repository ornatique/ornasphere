<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkerMetalLedger
{
    public function report(int|array $companyId, Request $request): array
    {
        $filters = $request->validate([
            'from_date' => ['nullable', 'date_format:Y-m-d'],
            'to_date' => array_filter(['nullable', 'date_format:Y-m-d', $request->filled('from_date') ? 'after_or_equal:from_date' : null]),
            'worker_id' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', 'in:detailed,daily'],
        ]);
        $issues = DB::table('jobwork_issue_items as line')
            ->join('jobwork_issues as header', 'header.id', '=', 'line.jobwork_issue_id')
            ->leftJoin('items as item', 'item.id', '=', 'line.item_id')
            ->whereIn('header.company_id', (array) $companyId)
            ->selectRaw("line.id, header.job_worker_id as worker_id, header.jobwork_date as date, header.voucher_no as voucher, item.item_name as item, COALESCE(item.metal, 'Unspecified') as metal, line.net_wt as debit, 0 as credit, 'Issue' as type");
        $receipts = DB::table('jobwork_receive_items as line')
            ->join('jobwork_receives as header', 'header.id', '=', 'line.jobwork_receive_id')
            ->leftJoin('jobwork_issues as issue', function ($join) use ($companyId) {
                $join->on('issue.id', '=', 'header.jobwork_issue_id')->whereIn('issue.company_id', (array) $companyId);
            })
            ->leftJoin('jobwork_issue_items as issued', 'issued.id', '=', 'line.jobwork_issue_item_id')
            ->leftJoin('items as item', 'item.id', '=', DB::raw('COALESCE(line.item_id, issued.item_id)'))
            ->whereIn('header.company_id', (array) $companyId)
            ->selectRaw("line.id, COALESCE(header.job_worker_id, issue.job_worker_id) as worker_id, header.receive_date as date, COALESCE(header.receive_no, issue.voucher_no) as voucher, item.item_name as item, COALESCE(item.metal, 'Unspecified') as metal, 0 as debit, line.receive_net_wt as credit, 'Receive' as type");
        $entries = $issues->unionAll($receipts);
        $cashOpenings = [];
        if (\Illuminate\Support\Facades\Schema::hasTable('customer_advance_ledgers') && \Illuminate\Support\Facades\Schema::hasColumn('job_workers', 'person_id')) {
            $openingQuery = DB::table('customer_advance_ledgers as opening')
                ->join('job_workers as ow', function ($join) use ($companyId) {
                    $join->on('ow.person_id', '=', 'opening.customer_id')->whereIn('ow.company_id', (array) $companyId);
                })->whereIn('opening.company_id', (array) $companyId)->where('opening.reference_type', 'person_opening');
            $entries->unionAll((clone $openingQuery)->whereNotNull('opening.metal_type')->selectRaw("opening.id, ow.id as worker_id, opening.entry_date as date, 'Opening' as voucher, 'Opening balance' as item, opening.metal_type as metal, opening.metal_out as debit, opening.metal_in as credit, 'Opening' as type"));
            $cashOpenings = (clone $openingQuery)->whereNull('opening.metal_type')
                ->when($filters['worker_id'] ?? null, fn($q,$id) => $q->where('ow.id',$id))
                ->when($filters['to_date'] ?? null, fn($q,$date) => $q->whereDate('opening.entry_date','<=',$date))
                ->selectRaw('ow.id as worker_id, ow.name as worker_name, opening.entry_date as date, opening.cash_out as debit, opening.cash_in as credit')->get()->toArray();
        }
        $query = DB::query()->fromSub($entries, 'entries')
            ->join('job_workers as worker', 'worker.id', '=', 'entries.worker_id')
            ->whereIn('worker.company_id', (array) $companyId)
            ->select('entries.*', 'worker.name as worker_name', 'worker.company_id')
            ->when($filters['worker_id'] ?? null, fn ($q, $id) => $q->where('worker_id', $id))
            ->when($filters['to_date'] ?? null, fn ($q, $date) => $q->whereDate('date', '<=', $date))
            ->orderBy('date')->orderBy('type')->orderBy('id');
        $filters['format'] = $filters['format'] ?? 'detailed';
        $groups = $this->balances($query->get(), $filters['from_date'] ?? null);
        if ($filters['format'] === 'daily') $groups = $this->daily($groups);
        return ['filters' => $filters, 'groups' => $groups, 'cash_openings' => $cashOpenings];
    }

    public function daily(array $groups): array
    {
        foreach ($groups as &$group) {
            $days = [];
            foreach ($group['rows'] as $row) {
                $date = substr($row['date'], 0, 10);
                $days[$date] ??= ['date' => $date, 'particulars' => 'Daily jobwork total', 'debit' => 0, 'credit' => 0];
                foreach (['debit', 'credit'] as $field) $days[$date][$field] = round($days[$date][$field] + $row[$field], 3);
            }
            ksort($days);
            $balance = $group['opening'];
            foreach ($days as &$day) {
                $balance = round($balance + $day['debit'] - $day['credit'], 3);
                $day['balance'] = $balance;
            }
            unset($day);
            $group['rows'] = array_values($days);
        }
        unset($group);
        return $groups;
    }

    public function balances(iterable $entries, ?string $from): array
    {
        $groups = [];
        foreach ($entries as $entry) {
            $e = (array) $entry;
            // Normalize only the report label; keep stored item data unchanged.
            $metal = preg_replace('/\s+/u', ' ', (string) ($e['metal'] ?? ''));
            $e['metal'] = \Illuminate\Support\Str::title(\Illuminate\Support\Str::lower(trim($metal))) ?: 'Unspecified';
            $key = $e['worker_id'].'|'.$e['metal'];
            if (!isset($groups[$key])) {
                $groups[$key] = ['company_id' => $e['company_id'] ?? null, 'worker_id' => $e['worker_id'], 'worker_name' => $e['worker_name'], 'metal' => $e['metal'],
                    'opening' => 0, 'debit' => 0, 'credit' => 0, 'closing' => 0,
                    'rows' => []];
            }
            $g = &$groups[$key];
            foreach (['debit', 'credit'] as $field) {
                $e[$field] = round((float) $e[$field], 3);
            }
            $g['closing'] = round($g['closing'] + $e['debit'] - $e['credit'], 3);
            if ($from && substr($e['date'], 0, 10) < $from) {
                $g['opening'] = $g['closing'];
            } else {
                foreach (['debit', 'credit'] as $field) $g[$field] = round($g[$field] + $e[$field], 3);
                $e['balance'] = $g['closing'];
                $g['rows'][] = $e;
            }
            unset($g);
        }
        return array_values($groups);
    }

    public function csv(array $ledger)
    {
        return response()->streamDownload(function () use ($ledger) {
            $out = fopen('php://output', 'w');
            $safe = fn ($v) => preg_match('/^[=+@\-\t\r]/', (string) $v) ? "'".$v : $v;
            fputcsv($out, ['Worker Metal Ledger', 'Weights in grams']);
            if (!empty($ledger['cash_openings'])) {
                fputcsv($out, ['Cash opening balances (Rs)', 'Date', 'Debit', 'Credit']);
                foreach ($ledger['cash_openings'] as $cash) fputcsv($out, [$safe($cash->worker_name), \Carbon\Carbon::parse($cash->date)->format('d-m-Y'), $cash->debit, $cash->credit]);
                fputcsv($out, []);
            }
            fputcsv($out, ['From', !empty($ledger['filters']['from_date']) ? \Carbon\Carbon::parse($ledger['filters']['from_date'])->format('d-m-Y') : 'Beginning', 'To', !empty($ledger['filters']['to_date']) ? \Carbon\Carbon::parse($ledger['filters']['to_date'])->format('d-m-Y') : 'Latest']);
            foreach ($ledger['groups'] as $g) {
                fputcsv($out, [$safe($g['worker_name']), $safe($g['metal']), 'Branch', $safe(ReportBranches::name($g['company_id'] ?? null))]);
                if (($ledger['filters']['format'] ?? 'detailed') === 'daily') {
                    fputcsv($out, ['Date', 'Particulars', 'Debit - Issued', 'Credit - Received', 'Net Balance (+Dr/-Cr)']);
                    fputcsv($out, ['', 'Opening balance', '', '', $g['opening']]);
                    foreach ($g['rows'] as $e) fputcsv($out, [\Carbon\Carbon::parse($e['date'])->format('d-m-Y'), $e['particulars'], $e['debit'], $e['credit'], $e['balance']]);
                    fputcsv($out, ['', 'Period totals / Closing', $g['debit'], $g['credit'], $g['closing']]);
                    continue;
                }
                fputcsv($out, ['Date', 'Voucher', 'Type', 'Item', 'Debit', 'Credit', 'Net Balance (+Dr/-Cr)']);
                fputcsv($out, ['', '', 'Opening', '', '', '', $g['opening']]);
                foreach ($g['rows'] as $e) fputcsv($out, [\Carbon\Carbon::parse($e['date'])->format('d-m-Y'), $safe($e['voucher']), $e['type'], $safe($e['item']), $e['debit'], $e['credit'], $e['balance']]);
                fputcsv($out, ['', '', 'Closing', '', $g['debit'], $g['credit'], $g['closing']]);
            }
            fclose($out);
        }, 'worker_metal_ledger.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
