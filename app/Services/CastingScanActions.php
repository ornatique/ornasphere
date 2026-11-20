<?php
namespace App\Services;

use App\Models\{User, VacuumVoucher};
use Illuminate\Support\Facades\Crypt;

class CastingScanActions
{
    // The caller must first authorize the voucher through VoucherScan::resolve.
    public static function menu(User $user, array $destination): array
    {
        $voucher = VacuumVoucher::where('company_id', $destination['company_id'])->findOrFail($destination['voucher_id']);
        $company = \App\Models\Company::findOrFail($voucher->company_id);
        $id = $voucher->id;
        $encrypted = Crypt::encryptString((string) $id);
        $query = '?'.http_build_query(['company_id'=>$voucher->company_id]);
        $actions = [];
        foreach ([
            ['casting-receive','Casting Receive','casting-release','metalIssueItems'],
            ['tree-cutting-issue','Tree Cutting Issue','tree-cutting-issue','releaseItems'],
            ['tree-cutting-receive','Tree Cutting Receive','tree-cutting-receive','treeCuttingIssueItems'],
            ['casting-sorting','Casting Sorting','casting-sorting','treeCuttingReceiveItems'],
        ] as [$module,$label,$web,$relation]) {
            $permissions = [];
            foreach (array_unique([$module,$web,str_replace('-', '_', $module)]) as $alias) {
                foreach (['view','manage'] as $verb) {
                    foreach (['-', '_', '.', ' '] as $separator) {
                        $permissions[] = $alias.$separator.$verb;
                        $permissions[] = $verb.$separator.$alias;
                    }
                }
            }
            $allowed = CompanyModules::permissionAllowed($company,$module)
                && ($user->hasRole('company_admin') || $user->hasAnyPermission($permissions));
            $ready = $voucher->{$relation}()->where('company_id',$voucher->company_id)->exists();
            $enabled = $allowed && $ready;
            $actions[] = ['key'=>str_replace('-','_',$module), 'label'=>$label, 'screen'=>str_replace('-','_',$module),
                'enabled'=>$enabled, 'reason'=>$allowed ? ($ready ? null : 'Complete the preceding stage first.') : 'You do not have access to this stage.',
                'method'=>'GET', 'voucher_id'=>$id, 'filters'=>['company_id'=>$voucher->company_id],
                'url'=>$enabled ? url('/api/'.$module.'/'.$id).$query : null,
                'web_url'=>$enabled ? route('company.'.$web.'.show',[$company->slug,$encrypted]) : null];
        }
        $issueAllowed = CompanyModules::permissionAllowed($company, 'casting-metal-issue')
            && ($user->hasRole('company_admin') || $user->hasAnyPermission(['casting-metal-issue-view','casting-metal-issue-manage','casting_metal_issue_view','casting_metal_issue_manage']));
        $actions[] = ['key'=>'view_issue_pdf','label'=>'View Casting Issue PDF','screen'=>'voucher_pdf',
            'enabled'=>$issueAllowed,'reason'=>$issueAllowed ? null : 'You do not have access to Casting Metal Issue.',
            'method'=>'GET','voucher_id'=>$id,'filters'=>['company_id'=>$voucher->company_id],
            'url'=>$issueAllowed ? url('/api/casting-metal-issue/'.$id.'/pdf').$query : null,
            'web_url'=>$issueAllowed ? route('company.casting-metal-issue.pdf',[$company->slug,$encrypted]) : null];
        foreach (['view_history'=>['View History',''], 'view_pdf'=>['View PDF','/pdf'], 'export_excel'=>['Export Excel','/excel']] as $key=>[$label,$suffix]) {
            $actions[] = ['key'=>$key,'label'=>$label,'enabled'=>true,'reason'=>null,'method'=>'GET',
                'url'=>url('/api/voucher-history/'.$id.$suffix).$query,
                'web_url'=>$suffix === '' ? route('company.voucher-history.index',[$company->slug,'voucher_id'=>$id]) : ($suffix === '/pdf' ? route('company.voucher-history.pdf',[$company->slug,$id]) : null)];
        }
        return array_merge($destination,['action'=>'choose','screen'=>'casting_voucher_actions','url'=>null,'actions'=>$actions]);
    }
}
