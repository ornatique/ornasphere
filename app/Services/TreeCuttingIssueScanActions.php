<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\VacuumVoucher;
use Illuminate\Support\Facades\Crypt;

class TreeCuttingIssueScanActions
{
    public static function menu(User $user, array $destination): array
    {
        $voucher = VacuumVoucher::where('company_id', $destination['company_id'])->findOrFail($destination['voucher_id']);
        $company = Company::findOrFail($voucher->company_id);
        $id = (int) $voucher->id;
        $companyId = (int) $voucher->company_id;
        $filters = ['company_id'=>$companyId];
        $query = '?'.http_build_query($filters);
        $encryptedId = Crypt::encryptString((string) $id);
        $actions = [
            ['key'=>'view_issue','label'=>'View Tree Cutting Issue','screen'=>'tree_cutting_issue_details','enabled'=>true,'method'=>'GET','voucher_id'=>$id,'filters'=>$filters,'url'=>url('/api/tree-cutting-issue/'.$id).$query,'web_url'=>route('company.tree-cutting-issue.show',[$company->slug,$encryptedId])],
            ['key'=>'view_issue_pdf','label'=>'View Tree Cutting Issue PDF','screen'=>'voucher_pdf','enabled'=>true,'method'=>'GET','voucher_id'=>$id,'filters'=>$filters,'url'=>url('/api/tree-cutting-issue/'.$id.'/pdf').$query,'web_url'=>route('company.tree-cutting-issue.pdf',[$company->slug,$encryptedId])],
            ['key'=>'view_receive','label'=>'Open Tree Cutting Receive','screen'=>'tree_cutting_receive','enabled'=>true,'method'=>'GET','voucher_id'=>$id,'filters'=>$filters+['voucher_id'=>$id],'url'=>url('/api/tree-cutting-receive').'?'.http_build_query($filters+['voucher_id'=>$id]),'web_url'=>route('company.tree-cutting-receive.index',[$company->slug,'voucher_id'=>$id])],
        ];
        return array_merge($destination,['action'=>'choose','screen'=>'tree_cutting_issue_actions','url'=>null,'actions'=>$actions]);
    }
}
