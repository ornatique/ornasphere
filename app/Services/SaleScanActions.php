<?php

namespace App\Services;

class SaleScanActions
{
    // The destination has already passed VoucherScan's company and permission checks.
    public static function menu(array $destination): array
    {
        $filters = ['company_id'=>$destination['company_id']];
        return array_merge($destination, [
            'action'=>'choose', 'screen'=>'sale_actions', 'url'=>null,
            'actions'=>[
                ['key'=>'view_pdf','label'=>'View PDF','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'voucher_pdf','url'=>$destination['url']],
                ['key'=>'view_sales_list','label'=>'View Sales List','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'sales_list','filters'=>$filters,'url'=>url('/api/sale-list').'?'.http_build_query($filters)],
            ],
        ]);
    }
}
