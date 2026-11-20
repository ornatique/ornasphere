<?php

namespace App\Services;

use App\Models\User;
use App\Models\VacuumVoucher;
use Illuminate\Support\Facades\Crypt;

class CastingMetalIssueScanActions
{
    /** Build the focused menu for a QR printed on a Casting Metal Issue voucher. */
    public static function menu(User $user, array $destination): array
    {
        $voucher = VacuumVoucher::where('company_id', $destination['company_id'])
            ->whereKey($destination['voucher_id'])
            ->firstOrFail();
        $company = \App\Models\Company::findOrFail($voucher->company_id);
        $id = (int) $voucher->id;
        $companyId = (int) $voucher->company_id;
        $encryptedId = Crypt::encryptString((string) $id);
        $query = '?' . http_build_query(['company_id' => $companyId]);

        $issueAllowed = self::canAccess($user, $company, 'casting-metal-issue', ['casting-metal-issue']);
        $receiveAllowed = self::canAccess($user, $company, 'casting-receive', ['casting-receive', 'casting-release']);
        $hasIssue = $voucher->metalIssueItems()->where('company_id', $companyId)->exists();
        $receiveEnabled = $receiveAllowed && $hasIssue;
        $receiveReason = !$receiveAllowed
            ? 'You do not have access to Casting Receive.'
            : ($hasIssue ? null : 'No Casting Metal Issue entries exist for this voucher yet.');

        $actions = [
            [
                'key' => 'view_casting_metal_issue',
                'label' => 'View Casting Metal Issue',
                'screen' => 'voucher_pdf',
                'enabled' => $issueAllowed,
                'reason' => $issueAllowed ? null : 'You do not have access to Casting Metal Issue.',
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => ['company_id' => $companyId],
                'url' => $issueAllowed ? url('/api/casting-metal-issue/' . $id . '/pdf') . $query : null,
                'web_url' => $issueAllowed ? route('company.casting-metal-issue.pdf', [$company->slug, $encryptedId]) : null,
            ],
            [
                'key' => 'casting_receive',
                'label' => 'Casting Receive',
                'screen' => 'casting_receive',
                'enabled' => $receiveEnabled,
                'reason' => $receiveReason,
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => ['company_id' => $companyId],
                'url' => $receiveEnabled ? url('/api/casting-receive/' . $id) . $query : null,
                'web_url' => $receiveEnabled ? route('company.casting-release.show', [$company->slug, $encryptedId]) : null,
            ],
            [
                'key' => 'casting_metal_issue_list',
                'label' => 'Casting Metal Issue List',
                'screen' => 'casting_metal_issue_list',
                'enabled' => $issueAllowed,
                'reason' => $issueAllowed ? null : 'You do not have access to Casting Metal Issue.',
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => ['company_id' => $companyId, 'voucher_id' => $id],
                'url' => $issueAllowed ? url('/api/casting-metal-issue') . '?' . http_build_query(['company_id' => $companyId, 'voucher_id' => $id]) : null,
                'web_url' => $issueAllowed ? route('company.casting-metal-issue.index', [$company->slug, 'voucher_id' => $id]) : null,
            ],
        ];

        return array_merge($destination, [
            'action' => 'choose',
            'screen' => 'casting_metal_issue_actions',
            'url' => null,
            'method' => 'GET',
            'requires_auth' => true,
            'filters' => ['company_id' => $companyId, 'can_manage' => $issueAllowed],
            'actions' => $actions,
        ]);
    }

    private static function canAccess(User $user, $company, string $module, array $permissionAliases): bool
    {
        $permissions = [];
        foreach (array_unique(array_merge([$module, str_replace('-', '_', $module)], $permissionAliases)) as $alias) {
            foreach (['view', 'manage'] as $verb) {
                foreach (['-', '_', '.', ' '] as $separator) {
                    $permissions[] = $alias . $separator . $verb;
                    $permissions[] = $verb . $separator . $alias;
                }
            }
        }

        return CompanyModules::permissionAllowed($company, $module)
            && ($user->hasRole('company_admin') || $user->hasAnyPermission($permissions));
    }
}
