<?php
namespace App\Services;

use App\Models\{JobworkReceive, JobworkIssue, User};

class JobworkReceiveScanActions
{
    public static function pdfPath(JobworkReceive $receive): string
    {
        return $receive->jobwork_issue_id
            ? '/api/jobwork-receive/pdf/'.$receive->jobwork_issue_id
            : '/api/jobwork-receive/direct/pdf/'.$receive->id;
    }

    public static function menu(User $user, array $destination): array
    {
        $receive = JobworkReceive::findOrFail($destination['voucher_id']);
        $issue = $receive->jobwork_issue_id ? JobworkIssue::where('company_id', $receive->company_id)->find($receive->jobwork_issue_id) : null;
        $canIssue = $issue && CompanyModules::permissionAllowed($user->company, 'jobwork-issue')
            && ($user->hasRole('company_admin') || $user->hasAnyPermission(['jobwork-issue-view','jobwork-issue-manage','jobwork_issue_view','jobwork_issue_manage']));
        $filters = ['company_id'=>$receive->company_id];
        if ($issue) $filters['issue_id'] = $issue->id;
        else $filters['status'] = 'direct';
        return array_merge($destination, [
            'action'=>'choose', 'screen'=>'jobwork_receive_actions', 'url'=>null,
            'issue_id'=>$issue?->id,
            'actions'=>[
                ['key'=>'view_issue_details','label'=>'View Issue Details','enabled'=>(bool)$canIssue,'reason'=>$canIssue ? null : ($issue ? 'You do not have access to Jobwork Issue.' : 'This receive has no linked issue.'),'method'=>'GET','screen'=>'jobwork_issue_details','url'=>$canIssue ? url('/api/jobwork-issues/'.$issue->id).'?company_id='.$receive->company_id : null],
                ['key'=>'view_pdf','label'=>'View PDF','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'voucher_pdf','url'=>$destination['url']],
                ['key'=>'view_receive_list','label'=>'View Receive List','enabled'=>true,'reason'=>null,'method'=>'GET','screen'=>'jobwork_receive_list','filters'=>$filters,'url'=>url('/api/jobwork-receive').'?'.http_build_query($filters)],
            ],
        ]);
    }
}
