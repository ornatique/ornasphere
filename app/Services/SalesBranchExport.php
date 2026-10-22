<?php
namespace App\Services;
use Illuminate\Http\Request;
class SalesBranchExport
{
    public const COLUMNS = [
        'sales' => ['company_name'=>'Company / Branch', 'voucher_no'=>'Voucher No', 'sale_date'=>'Sale Date', 'customer_name'=>'Customer Name', 'total_qty'=>'Total Qty', 'total_gross_weight'=>'Total Gross Weight', 'total_net_weight'=>'Total Net Weight', 'total_fine_weight'=>'Total Fine Weight', 'total_metal_amount'=>'Total Metal Amount', 'total_labour_amount'=>'Total Labour Amount', 'total_other_amount'=>'Total Other Amount', 'net_total'=>'Net Total', 'received_amount_fmt'=>'Received Amount Fmt', 'refund_paid_amount_fmt'=>'Refund Paid Amount Fmt', 'pending_amount_fmt'=>'Pending Amount Fmt', 'creator_name'=>'Creator Name', 'modified_at'=>'Modified At', 'modified_count'=>'Modified Count'],
        'approval' => ['company_name'=>'Company / Branch', 'approval_no'=>'Approval No', 'approval_date'=>'Approval Date', 'customer_name'=>'Customer Name', 'item_names'=>'Item Names', 'qr_code'=>'Qr Code', 'total_qty'=>'Total Qty', 'total_gross_weight'=>'Total Gross Weight', 'total_other_weight'=>'Total Other Weight', 'total_net_weight'=>'Total Net Weight', 'total_fine_weight'=>'Total Fine Weight', 'total_metal_amount'=>'Total Metal Amount', 'total_labour_amount'=>'Total Labour Amount', 'total_other_amount'=>'Total Other Amount', 'total_amount'=>'Total Amount', 'creator_name'=>'Creator Name', 'modified_at'=>'Modified At', 'modified_count'=>'Modified Count'],
        'approval-returns' => ['company_name'=>'Company / Branch', 'voucher_no'=>'Voucher No', 'customer_name'=>'Customer Name', 'return_date'=>'Return Date', 'item_names'=>'Item Names', 'qty'=>'Qty', 'gross_wt'=>'Gross Wt', 'net_wt'=>'Net Wt', 'fine_wt'=>'Fine Wt', 'metal_amt'=>'Metal Amt', 'labour_amt'=>'Labour Amt', 'other_amt'=>'Other Amt', 'total_amt'=>'Total Amt', 'created_by'=>'Created By'],
        'receive-purchase' => ['company_name'=>'Company / Branch', 'voucher_no'=>'Voucher No', 'voucher_date'=>'Voucher Date', 'customer_name'=>'Customer Name', 'entry_type'=>'Entry Type', 'payment_mode_label'=>'Payment Mode Label', 'cash_in'=>'Cash In', 'cash_out'=>'Cash Out', 'metal_type_label'=>'Metal Type Label', 'metal_in'=>'Metal In', 'metal_out'=>'Metal Out', 'rate'=>'Rate', 'amount'=>'Amount'],
    ];
    public static function respond($table, Request $request, $companies, string $module)
    {
        if (!$request->filled('export')) return $table->make(true);
        $request->validate(['export'=>'required|in:pdf,excel']);
        $data=$table->skipPaging()->make(true)->getData(true);
        abort_if(isset($data['error']),422,'Unable to generate export.');
        return CastingListExport::download($request,$companies,ucwords(str_replace('-',' ',$module)),self::COLUMNS[$module],$data['data'],'sales-report');
    }
    public static function downloadApi(Request $request,$companies,string $title,$rows)
    {
        $columns=['company_name'=>'Company / Branch'];
        foreach ($rows as $row) foreach ($row as $key=>$value) {
            if ($key === 'company_name' || $key === 'id' || str_ends_with($key,'_id') || str_contains($key,'image') || is_array($value)) continue;
            $columns[$key]=ucwords(str_replace('_',' ',$key));
        }
        return CastingListExport::download($request,$companies,$title,$columns,$rows,'sales-report');
    }
}
