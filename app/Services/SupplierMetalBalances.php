<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;

class SupplierMetalBalances
{
    public static function rows(array $companyIds, ?int $supplierId = null): Collection
    {
        return DB::table('customer_advance_ledgers as l')
            ->join('customers as c', function ($join) {
                $join->on('c.id', '=', 'l.customer_id')->on('c.company_id', '=', 'l.company_id');
            })
            ->join('category_people as p', function ($join) {
                $join->on('p.id', '=', 'c.category_person_id')->on('p.company_id', '=', 'c.company_id');
            })
            ->join('companies as co', 'co.id', '=', 'l.company_id')
            ->whereIn('l.company_id', $companyIds)
            ->whereRaw('LOWER(TRIM(p.category_name)) = ?', ['bullion'])
            ->when($supplierId, fn ($q) => $q->where('c.id', $supplierId))
            ->select('c.id as supplier_id', 'c.name as supplier_name', 'l.company_id', 'co.name as branch_name')
            ->selectRaw('SUM(COALESCE(l.cash_in,0) - COALESCE(l.cash_out,0)) as cash_balance')
            ->selectRaw("SUM(CASE WHEN l.metal_type = 'gold' THEN COALESCE(l.metal_in,0) - COALESCE(l.metal_out,0) ELSE 0 END) as gold_balance")
            ->selectRaw("SUM(CASE WHEN l.metal_type = 'silver' THEN COALESCE(l.metal_in,0) - COALESCE(l.metal_out,0) ELSE 0 END) as silver_balance")
            ->groupBy('c.id', 'c.name', 'l.company_id', 'co.name')->orderBy('c.name')->get();
    }
}
