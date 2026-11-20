<?php
namespace App\Services;

use App\Models\User;

class JobworkIssueScanActions
{
    public static function menu(User $user, array $destination): array
    {
        $id = $destination['voucher_id'];
        $companyId = $destination['company_id'];
        $enabled = CompanyModules::permissionAllowed($user->company, 'jobwork-receive')
            && ($user->hasRole('company_admin') || $user->hasAnyPermission(['jobwork-receive-view', 'jobwork-receive-manage', 'jobwork_receive_view', 'jobwork_receive_manage']));
        $filters = ['company_id'=>$companyId, 'issue_id'=>$id];
        return array_merge($destination, [
            'action'=>'choose', 'screen'=>'jobwork_issue_actions', 'url'=>null,
            'actions'=>[
                ['key'=>'view_pdf','label'=>'View PDF','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'voucher_pdf','url'=>url('/api/jobwork-issues/export/pdf/'.$id).'?company_id='.$companyId],
                ['key'=>'view_issue_list','label'=>'View Issue List','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'jobwork_issue_list','url'=>$destination['url'],'filters'=>$destination['filters']],
                ['key'=>'view_receive','label'=>'View Receive','enabled'=>$enabled,'reason'=>$enabled ? null : 'You do not have access to Jobwork Receive.','method'=>'GET','screen'=>'jobwork_receive_list','url'=>$enabled ? url('/api/jobwork-receive').'?'.http_build_query($filters) : null,'filters'=>$filters],
            ],
        ]);
    }
}
