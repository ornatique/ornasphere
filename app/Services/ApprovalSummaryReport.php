<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApprovalSummaryReport
{
    public static function metrics(): array
    {
        $metrics = [];
        foreach (['issued', 'returned', 'sold', 'pending'] as $status) {
            foreach (['qty' => 'Qty', 'gross' => 'Gross Wt', 'other' => 'Other Wt', 'net' => 'Net Wt', 'fine' => 'Fine Wt'] as $key => $label) {
                $metrics[$status.'_'.$key] = ucfirst($status).' '.$label;
            }
        }
        return $metrics;
    }

    public static function query(int|array $companyId, array $filters = [])
    {
        $items = DB::table('approval_items')->select('approval_id')->groupBy('approval_id');
        foreach (['issued', 'returned', 'sold', 'pending'] as $status) {
            $condition = $status === 'issued' ? '1=1' : ($status === 'pending' ? "(status = 'pending' OR status IS NULL)" : "status = '$status'");
            foreach (['qty' => '1', 'gross' => 'gross_weight', 'other' => 'other_weight', 'net' => 'net_weight', 'fine' => 'total_fine_weight'] as $key => $column) {
                $items->selectRaw("SUM(CASE WHEN $condition THEN COALESCE($column, 0) ELSE 0 END) AS {$status}_{$key}");
            }
        }
        $query = DB::table('approval_headers as h')
            ->leftJoinSub($items, 'm', 'm.approval_id', '=', 'h.id')
            ->leftJoin('customers as c', function ($join) { $join->on('c.id', '=', 'h.customer_id')->on('c.company_id', '=', 'h.company_id'); })
            ->whereIn('h.company_id', (array) $companyId)
            ->select('h.company_id', 'h.id', 'h.approval_no', 'h.approval_date', 'c.name as customer_name');
        foreach (self::metrics() as $key => $label) $query->selectRaw("COALESCE(m.$key, 0) AS $key");
        if (!empty($filters['customer_id'])) $query->where('h.customer_id', $filters['customer_id']);
        if (!empty($filters['from_date'])) $query->whereDate('h.approval_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('h.approval_date', '<=', $filters['to_date']);
        if (!empty($filters['voucher'])) $query->where('h.approval_no', 'like', '%'.$filters['voucher'].'%');
        return $query->orderByDesc('h.approval_date')->orderByDesc('h.id');
    }
}
