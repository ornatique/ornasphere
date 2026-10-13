<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ApprovalReturnRegister
{
    public static function query(int $companyId, array $filters = [])
    {
        $issues = DB::table('approval_headers as h')
            ->leftJoin('approval_items as a', 'a.approval_id', '=', 'h.id')
            ->where('h.company_id', $companyId)
            ->selectRaw("h.id AS voucher_id, 'Approval' AS entry_type, h.approval_date AS entry_date, h.approval_no AS voucher_no, h.approval_no AS approval_no, h.customer_id, COUNT(a.id) AS qty, COALESCE(SUM(a.gross_weight),0) AS gross, COALESCE(SUM(a.other_weight),0) AS other, COALESCE(SUM(a.net_weight),0) AS net, COALESCE(SUM(a.total_fine_weight),0) AS fine, COALESCE(SUM(a.total_amount),0) AS amount")
            ->groupBy('h.id', 'h.approval_date', 'h.approval_no', 'h.customer_id');

        // A return voucher can include items from several approval vouchers.
        // Group by the actual item source, rather than assigning all rows to source_id.
        $returns = DB::table('sale_returns as r')
            ->join('sale_return_items as ri', 'ri.sale_return_id', '=', 'r.id')
            ->leftJoin('approval_items as a', 'a.id', '=', 'ri.approval_item_id')
            ->join('approval_headers as h', function ($join) {
                $join->on('h.id', '=', DB::raw('COALESCE(a.approval_id, r.source_id)'));
            })
            ->where('r.company_id', $companyId)->where('h.company_id', $companyId)
            ->where('r.source_type', 'approval')
            ->selectRaw("r.id AS voucher_id, 'Approval Return' AS entry_type, r.return_date AS entry_date, r.return_voucher_no AS voucher_no, h.approval_no AS approval_no, h.customer_id, COUNT(ri.id) AS qty, COALESCE(SUM(a.gross_weight),0) AS gross, COALESCE(SUM(a.other_weight),0) AS other, COALESCE(SUM(a.net_weight),0) AS net, COALESCE(SUM(a.total_fine_weight),0) AS fine, COALESCE(SUM(ri.return_amount),0) AS amount")
            ->groupBy('r.id', 'r.return_date', 'r.return_voucher_no', 'h.approval_no', 'h.customer_id');

        $sales = DB::table('sales as s')
            ->join('sale_items as si', 'si.sale_id', '=', 's.id')
            ->join('approval_items as a', 'a.id', '=', 'si.approval_item_id')
            ->join('approval_headers as h', 'h.id', '=', 'a.approval_id')
            ->where('s.company_id', $companyId)->where('h.company_id', $companyId)
            ->selectRaw("s.id AS voucher_id, 'Sale from Approval' AS entry_type, s.sale_date AS entry_date, s.voucher_no AS voucher_no, h.approval_no AS approval_no, s.customer_id, COUNT(si.id) AS qty, COALESCE(SUM(si.gross_weight),0) AS gross, COALESCE(SUM(si.other_weight),0) AS other, COALESCE(SUM(si.net_weight),0) AS net, COALESCE(SUM(si.fine_weight),0) AS fine, COALESCE(SUM(si.total_amount),0) AS amount")
            ->groupBy('s.id', 's.sale_date', 's.voucher_no', 'h.approval_no', 's.customer_id');

        $query = DB::query()->fromSub($issues->unionAll($returns)->unionAll($sales), 'register')
            ->leftJoin('customers as c', function ($join) use ($companyId) {
                $join->on('c.id', '=', 'register.customer_id')->where('c.company_id', $companyId);
            })->select('register.*', 'c.name as customer_name');
        if (!empty($filters['customer_id'])) $query->where('register.customer_id', $filters['customer_id']);
        if (!empty($filters['from_date'])) $query->whereDate('entry_date', '>=', $filters['from_date']);
        if (!empty($filters['to_date'])) $query->whereDate('entry_date', '<=', $filters['to_date']);
        if (!empty($filters['entry_type'])) $query->where('entry_type', $filters['entry_type']);
        if (!empty($filters['voucher'])) $query->where(function ($q) use ($filters) {
            $q->where('voucher_no', 'like', '%'.$filters['voucher'].'%')->orWhere('approval_no', 'like', '%'.$filters['voucher'].'%');
        });
        if (!empty($filters['search'])) $query->where(function ($q) use ($filters) {
            $term = '%'.$filters['search'].'%';
            $q->where('voucher_no', 'like', $term)->orWhere('approval_no', 'like', $term)->orWhere('c.name', 'like', $term)->orWhere('entry_type', 'like', $term);
        });
        return $query->orderByDesc('entry_date')->orderByDesc('voucher_id')->orderBy('entry_type')->orderBy('approval_no');
    }
}
