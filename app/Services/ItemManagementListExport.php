<?php
namespace App\Services;
use Illuminate\Http\Request;
class ItemManagementListExport
{
    public const TOTALS = ['gross_weight'=>3, 'other_weight'=>3, 'other'=>3, 'net_weight'=>3, 'other_charges'=>2, 'sale_other'=>2, 'qty_pcs'=>0, 'total_pcs'=>0];
    public const COLUMNS = [
        'items' => ['company_name'=>'Company / Branch', 'item_name'=>'Item', 'item_code'=>'Code', 'metal'=>'Metal', 'labour_type'=>'Labour Type'],
        'label-config' => ['company_name'=>'Company / Branch', 'item_name'=>'Item', 'prefix'=>'Prefix'],
        'other-charges' => ['company_name'=>'Company / Branch', 'other_charge'=>'Name', 'code'=>'Code', 'default_amount'=>'Default Amount'],
        'label-items' => ['company_name'=>'Company / Branch', 'date'=>'Date', 'item_name'=>'Item', 'qr_code'=>'Label Code', 'gross_weight'=>'Gross Wt', 'other_weight'=>'Other Wt', 'other_charges'=>'Other Charges', 'net_weight'=>'Net Wt', 'qty_pcs'=>'Quantity', 'printed_at'=>'Printed At'],
        'label-printing' => ['company_name'=>'Company / Branch', 'item_name'=>'Item', 'label_code'=>'Label Code', 'gross_weight'=>'Gross Wt', 'other_weight'=>'Other Wt', 'net_weight'=>'Net Wt', 'sale_other'=>'Other Charges', 'date_time'=>'Printed At'],
    ];
    public static function respond($table, Request $request, $companies, string $module)
    {
        if (!$request->filled('export')) return $table->make(true);
        $request->validate(['export'=>'required|in:pdf,excel']);
        $data=$table->skipPaging()->make(true)->getData(true);
        abort_if(isset($data['error']),422,'Unable to generate export.');
        return CastingListExport::download($request,$companies,ucwords(str_replace('-',' ',$module)),self::COLUMNS[$module],$data['data'],'item-management-report', in_array($module, ['label-items','label-printing']) ? self::TOTALS : []);
    }
    public static function downloadApi(Request $request,$companies,string $title,$rows)
    {
        $columns=['company_name'=>'Company / Branch'];
        foreach ($rows as $row) foreach ($row as $key=>$value) {
            if ($key === 'company_name' || $key === 'id' || str_ends_with($key,'_id') || str_contains($key,'image') || is_array($value)) continue;
            $columns[$key]=ucwords(str_replace('_',' ',$key));
        }
        return CastingListExport::download($request,$companies,$title,$columns,$rows,'item-management-report', array_intersect_key(self::TOTALS, $columns));
    }
}
