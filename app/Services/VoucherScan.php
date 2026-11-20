<?php

namespace App\Services;

use App\Models\{ApprovalHeader, Sale, SaleReturn, JobworkIssue, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

class VoucherScan
{
    public const TYPES = [
        'casting' => [\App\Models\VacuumVoucher::class, 'voucher_no', 'voucher-history', 'details', 'voucher-history/%d'],
        'casting-metal-issue' => [\App\Models\VacuumVoucher::class, 'voucher_no', 'casting-metal-issue', 'details', 'casting-metal-issue/%d'],
        'tree-cutting-receive' => [\App\Models\VacuumVoucher::class, 'voucher_no', 'tree-cutting-receive', 'details', 'tree-cutting-receive/%d'],
        'tree-cutting-issue' => [\App\Models\VacuumVoucher::class, 'voucher_no', 'tree-cutting-issue', 'details', 'tree-cutting-issue/%d'],
        'approval' => [ApprovalHeader::class, 'approval_no', 'approval', 'pdf', 'approvals/%d/pdf'],
        'sale' => [Sale::class, 'voucher_no', 'sale', 'pdf', 'sales/%d/pdf'],
        'sale-return' => [SaleReturn::class, 'return_voucher_no', 'approval-return', 'pdf', 'returns/pdf/%d'],
        'jobwork-receive' => [\App\Models\JobworkReceive::class, 'receive_no', 'jobwork-receive', 'pdf', 'jobwork-receive/pdf/%d'],
        'jobwork-issue' => [JobworkIssue::class, 'voucher_no', 'jobwork-issue', 'list', 'jobwork-issues'],
    ];

    public static function decode(string $value): array
    {
        $request = Request::create($value);
        // Only our signed voucher URLs are accepted. Never fetch a scanned URL.
        abort_unless($request->getSchemeAndHttpHost() === Request::create(url('/'))->getSchemeAndHttpHost(), 422, 'Unsupported QR code.');
        abort_unless(URL::hasValidSignature($request), 422, 'Invalid or altered voucher QR code.');
        $base = rtrim(Request::create(url('/'))->getPathInfo(), '/');
        abort_unless(preg_match('#^'.preg_quote($base, '#').'/vouchers/(approval|sale|sale-return|jobwork-issue|jobwork-receive|casting-metal-issue|tree-cutting-receive|casting|tree-cutting-issue)/([1-9][0-9]*)$#', $request->getPathInfo(), $matches), 422, 'Unsupported voucher QR code.');
        return [$matches[1], (int) $matches[2]];
    }

    public static function resolve(User $user, string $type, int $id): array
    {
        abort_unless(isset(self::TYPES[$type]), 422, 'Unsupported voucher type.');
        [$model, $number, $permission, $action, $path] = self::TYPES[$type];
        $companies = CompanyBranches::visibleCompanies($user)->filter(fn ($c) => CompanyBranches::isActive($c));
        $record = $model::whereIn('company_id', $companies->pluck('id'))->findOrFail($id);
        $company = $companies->firstWhere('id', $record->company_id);
        if (!CompanyModules::permissionAllowed($company, $permission)) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(response()->json(['success'=>false, 'code'=>'MODULE_NOT_ENABLED', 'message'=>'This module is not enabled for your company.'], 403));
        }
        abort_if(CompanyPlanService::status($company)['expired'], 403, 'Company subscription has expired.');
        $candidates = [];
        foreach ([$permission, str_replace('-', '_', $permission), str_replace('-', '', $permission)] as $p) {
            foreach (['view', 'manage'] as $verb) {
                foreach (['-', '_', '.', ' '] as $separator) {
                    $candidates[] = $p.$separator.$verb;
                    $candidates[] = $verb.$separator.$p;
                }
            }
        }
        abort_unless($user->hasRole('company_admin') || $user->hasAnyPermission($candidates), 403, 'You do not have permission to view this voucher.');
        $filters = ['company_id' => (int) $record->company_id];
        if ($action === 'list') $filters['voucher_id'] = (int) $record->id;
        if ($type === 'approval') $filters['status'] = 'all';
        if ($type === 'jobwork-receive') $path = ltrim(JobworkReceiveScanActions::pdfPath($record), '/');
        if ($type === 'jobwork-receive') $path = substr($path, 4);
        $destination = [
            'voucher_type'=>$type, 'voucher_id'=>(int)$record->id, 'voucher_no'=>$record->$number,
            'company_id'=>(int)$record->company_id, 'action'=>$action,
            'screen'=>in_array($type, ['casting', 'casting-metal-issue', 'tree-cutting-receive', 'tree-cutting-issue'], true) ? str_replace('-', '_', $type).'_actions' : ($action === 'pdf' ? 'voucher_pdf' : 'jobwork_issue_list'),
            'url'=>url('/api/'.sprintf($path, $id)).'?'.http_build_query($filters),
            'method'=>'GET', 'requires_auth'=>true, 'filters'=>$filters,
        ];

        // A Tree Cutting Receive QR is action-oriented by definition. Build
        // the menu here so every API/web caller gets the same response shape.
        if ($type === 'tree-cutting-receive') {
            return TreeCuttingReceiveScanActions::menu($user, $destination);
        }

        return $destination;
    }
}
