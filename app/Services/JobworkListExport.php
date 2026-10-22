<?php
namespace App\Services;
use Illuminate\Http\Request;
class JobworkListExport
{
    public const MODULES = [
'labour-formula' => ['title'=>'Labour Formula', 'columns'=>['company_name'=>'Company / Branch', 'name'=>'Name', 'status_badge'=>'Status']],
'production-cost' => ['title'=>'Production Cost', 'columns'=>['company_name'=>'Company / Branch', 'name'=>'Name', 'status_badge'=>'Status']],
'production-step' => ['title'=>'Production Step', 'columns'=>['company_name'=>'Company / Branch', 'name'=>'Step Name', 'labour_formula_name'=>'Labour Formula', 'receivable_loss_badge'=>'Receivable Loss', 'production_cost_name'=>'Production Cost', 'remarks'=>'Remarks', 'assigned_users_count'=>'Assigned Users', 'modified_at_view'=>'Modified', 'modified_count'=>'Modified Count', 'user_name'=>'Created By', 'created_at_view'=>'Created at']],
'jobwork-issue' => ['title'=>'Jobwork Issue', 'columns'=>['company_name'=>'Company / Branch', 'voucher_no'=>'Voucher No', 'jobwork_date_view'=>'Voucher Date', 'jobworker_name'=>'Jobworker', 'production_step_name'=>'Production Step', 'gross_wt_sum'=>'Gross Wt', 'net_wt_sum'=>'Net Wt', 'fine_wt_sum'=>'Fine Wt', 'total_amt_sum'=>'Total Amt', 'modified_at_view'=>'Modified', 'modified_count'=>'Modified Count', 'user_name'=>'Created By', 'created_at_view'=>'Created at']],
'jobwork-receive' => ['title'=>'Jobwork Receive', 'columns'=>['company_name'=>'Company / Branch', 'voucher_no'=>'Voucher No', 'jobwork_date_view'=>'Voucher Date', 'jobworker_name'=>'Customer / Jobworker', 'production_step_name'=>'Production Step', 'issue_net_wt_sum'=>'Issue Net Wt', 'receive_net_wt_sum'=>'Receive Net Wt', 'pending_net_wt'=>'Pending Net Wt', 'status'=>'Status']],
    ];
    public static function respond($table, Request $request, $companies, string $module)
    {
        if (!$request->filled('export')) return $table->make(true);
        $request->validate(['export'=>'required|in:pdf,excel']);
        $data=$table->skipPaging()->make(true)->getData(true);
        abort_if(isset($data['error']),422,'Unable to generate export.');
        return self::download($request,$companies,$module,$data['data']);
    }
    public static function download(Request $request,$companies,string $module,$rows)
    {
        $config=self::MODULES[$module];
        return CastingListExport::download($request,$companies,$config['title'],$config['columns'],$rows,'jobwork-report');
    }
}
