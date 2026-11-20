<?php

namespace App\Services;

use App\Models\SaleReturn;

class ReturnScanActions
{
    // Called only after VoucherScan::resolve has authorized the record.
    public static function menu(int $id, array $destination): array
    {
        $return = SaleReturn::findOrFail($id);
        if (!in_array($return->source_type, ['approval', 'mixed'], true)) return $destination;
        $filters = ['company_id'=>(int)$return->company_id, 'voucher_id'=>$id];
        return array_merge($destination, [
            'action'=>'choose', 'screen'=>'approval_return_actions', 'url'=>null,
            'summary'=>['source_type'=>$return->source_type, 'date'=>$return->return_date ? \Carbon\Carbon::parse($return->return_date)->format('Y-m-d') : null],
            'actions'=>[
                ['key'=>'view_pdf','label'=>'View PDF','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'voucher_pdf','url'=>$destination['url']],
                ['key'=>'view_return_list','label'=>'View Return List','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'approval_return_list','filters'=>$filters,'url'=>url('/api/returns').'?'.http_build_query($filters)],
                ['key'=>'view_return_details','label'=>'View Return Details','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'approval_return_details','url'=>url('/api/returns/'.$id).'?company_id='.$return->company_id],
            ],
        ]);
    }
}
