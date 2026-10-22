<?php
namespace App\Services;

use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;

class CastingListExport
{
    public const MODULES = [
        'vacuum-buchs' => ['title' => 'Vacuum Buch List', 'columns' => ['company_name' => 'Company / Branch', 'buch_no' => 'Buch No', 'size_inch' => 'Size (Inch)', 'weight' => 'Weight', 'weight_change_view' => 'Weight Status', 'modified_at_view' => 'Modified', 'modified_count' => 'Modified Count', 'user_name' => 'Created By', 'created_at_view' => 'Created At']], 
        'vacuum-processes' => ['title' => 'Vacuum Process List', 'columns' => ['company_name' => 'Company / Branch', 'name' => 'Name', 'modified_at_view' => 'Modified', 'modified_count' => 'Modified Count', 'user_name' => 'Created By', 'created_at_view' => 'Created At']], 
        'vacuum-vouchers' => ['title' => 'Vacuum Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no' => 'Voucher No', 'voucher_date_view' => 'Date', 'process_name' => 'Process', 'worker_name' => 'Worker', 'gross_wt_total_view' => 'Gross Wt', 'buch_wt_total_view' => 'Buch Wt', 'net_wt_total_view' => 'Net Wt', 'silver_wt_total_view' => 'Silver Wt', 'modified_at_view' => 'Modified', 'modified_count' => 'Modified Count', 'user_name' => 'Created By', 'created_at_view' => 'Created At']], 
        'casting-heating' => ['title' => 'Casting Heating Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date & Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'total_pcs' => 'Total Pcs', 'in_bhati_pcs' => 'In Bhati Pcs', 'created_at_view' => 'Created At']], 
        'casting-metal-issue' => ['title' => 'Casting Metal Issue Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'assigned_metal_view' => 'Assigned Metal', 'metal_weight_total_view' => 'Metal Total Wt', 'issue_silver_wt_total_view' => 'Issue Silver Wt', 'pending_metal_view' => 'Pending']], 
        'casting-release' => ['title' => 'Casting Receive Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'assigned_receive_view' => 'Assigned Receive', 'release_tree_wt_view' => 'Release Tree Wt', 'release_tree_bhuko_view' => 'Release Tree Bhuko', 'loss_view' => 'Loss', 'pending_receive_view' => 'Pending']], 
        'tree-cutting-office' => ['title' => 'Tree Cutting Issue Office Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'office_used_view' => 'Office Used', 'tree_wt_view' => 'Tree Wt', 'office_cut_wt_view' => 'Office Cut Wt', 'remaining_tree_wt_view' => 'Remaining Tree Wt']], 
        'tree-cutting-issue' => ['title' => 'Tree Cutting Issue Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'assigned_tree_cutting_view' => 'Assigned Issue', 'pending_tree_cutting_view' => 'Pending', 'office_cut_wt_view' => 'Office Cut Wt', 'receive_tree_wt_view' => 'Receive Tree Wt']], 
        'tree-cutting-receive' => ['title' => 'Tree Cutting Receive Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'assigned_receive_view' => 'Assigned Receive', 'pending_receive_view' => 'Pending', 'office_cut_wt_view' => 'Office Cut Wt', 'issue_tree_wt_view' => 'Issue Tree Wt', 'receive_pc_wt_view' => 'Receive Pc Wt', 'receive_tree_bhuko_view' => 'Receive Tree Bhuko', 'loss_view' => 'Loss']], 
        'casting-sorting' => ['title' => 'Casting Sorting Voucher List', 'columns' => ['company_name' => 'Company / Branch', 'voucher_no_view' => 'Voucher Number', 'date_time_view' => 'Date Time', 'process_name' => 'Process', 'worker_name' => 'Worker Name', 'total_pcs_view' => 'Total Pcs', 'sorting_weight_view' => 'Sorting Wt', 'sorting_quantity_view' => 'Quantity']], 
    ];

    public static function respond($table, Request $request, $companies, string $module)
    {
        if (!$request->filled('export')) return $table->make(true);
        $request->validate(['export' => 'required|in:pdf,excel']);
        $response = $table->skipPaging()->make(true)->getData(true);
        abort_if(isset($response['error']), 422, 'Unable to generate export.');
        $config = self::MODULES[$module];
        return self::download($request, $companies, $config['title'], $config['columns'], $response['data']);
    }

    public static function download(Request $request, $companies, string $title, array $columns, $records, string $filename = 'casting-report', array $totalColumns = [])
    {
        $request->validate(['export' => 'required|in:pdf,excel']);
        $branches = $companies->pluck('name')->implode(' / ');
        $filters = collect($request->only(['from_date', 'to_date', 'worker_id']))->filter()->map(fn ($v, $k) => $k.': '.$v)->implode(' | ');
        $rows = collect($records)->map(fn ($record) => collect($columns)->map(fn ($label, $key) => html_entity_decode(strip_tags((string) data_get($record, $key, '')), ENT_QUOTES | ENT_HTML5, 'UTF-8'))->values()->all())->all();
        $totals = [];
        if ($totalColumns) {
            foreach (array_keys($columns) as $index => $key) {
                $totals[] = array_key_exists($key, $totalColumns)
                    ? number_format(array_sum(array_map(fn ($row) => (float) str_replace(',', '', $row[$index]), $rows)), $totalColumns[$key], '.', ',')
                    : ($index === 0 ? 'Total' : '');
            }
        }
        $headers = array_values($columns);
        if ($request->input('export') === 'pdf') {
            return Pdf::loadView('company.casting_exports.list', compact('title', 'branches', 'filters', 'headers', 'rows', 'totals'))
                ->setPaper(count($headers) > 10 ? 'a3' : 'a4', 'landscape')->download($filename.'.pdf');
        }
        if ($totals) $rows[] = $totals;
        return ReportXlsx::download([[$title], [$branches], [$filters], $headers, ...$rows], $filename.'.xlsx', 'Report');
    }
}
