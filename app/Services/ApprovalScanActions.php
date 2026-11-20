<?php

namespace App\Services;

use App\Models\{ApprovalHeader, User};

class ApprovalScanActions
{
    public static function pending(ApprovalHeader $approval)
    {
        return $approval->items()->where(fn ($q) => $q->whereNull('status')->orWhereRaw('LOWER(TRIM(status)) = ?', ['pending']));
    }

    public static function returnReason(User $user, ApprovalHeader $approval): ?string
    {
        if (!BranchReadOnly::canWrite($user, $approval->company_id)) return 'This branch voucher is read-only. Return items from its own branch.';
        if (!CompanyModules::permissionAllowed($user->company, 'approval-return')) return 'The approval return module is not enabled.';
        $permissions = [];
        foreach (['approval-return', 'approval_return', 'approvalreturn'] as $module) {
            foreach (['create', 'manage'] as $action) {
                foreach (['-', '_', '.', ' '] as $separator) {
                    $permissions[] = $module.$separator.$action;
                    $permissions[] = $action.$separator.$module;
                }
            }
        }
        if (!$user->hasRole('company_admin') && !$user->hasAnyPermission($permissions)) return 'You do not have permission to return items.';
        if (!self::pending($approval)->exists()) return 'No items available for return.';
        return null;
    }

    public static function menu(User $user, int $id, array $destination): array
    {
        // Caller must resolve the voucher first to enforce visibility and view permission.
        $approval = ApprovalHeader::with('customer', 'company')->findOrFail($id);
        $reason = self::returnReason($user, $approval);
        $query = '?company_id='.$approval->company_id;
        return array_merge($destination, [
            'action'=>'choose', 'screen'=>'approval_actions', 'url'=>null,
            'summary'=>[
                'customer_id'=>$approval->customer_id,
                'customer_name'=>$approval->customer?->name,
                'company_name'=>$approval->company?->name,
                'date'=>$approval->approval_date?->format('Y-m-d'),
                'status'=>$approval->status,
                'returnable_items_count'=>self::pending($approval)->count(),
            ],
            'actions'=>[
                ['key'=>'view_pdf','label'=>'View PDF','enabled'=>true,'reason'=>null,'screen'=>'voucher_pdf','method'=>'GET','url'=>$destination['url']],
                ['key'=>'return_items','label'=>'Return Items','enabled'=>$reason === null,'reason'=>$reason,'screen'=>'approval_return','method'=>'GET',
                    'url'=>$reason === null ? url('/api/approvals/'.$id.'/return-options').$query : null],
                ['key'=>'view_approval','label'=>'View Approval','enabled'=>true,'reason'=>null,'screen'=>'approval_details','method'=>'GET','url'=>url('/api/approvals/'.$id).$query],
            ],
        ]);
    }
}
