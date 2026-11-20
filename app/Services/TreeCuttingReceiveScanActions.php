<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use App\Models\VacuumVoucher;
use Illuminate\Support\Facades\Crypt;

class TreeCuttingReceiveScanActions
{
    /** Focused actions for a QR printed on a Tree Cutting Receive voucher. */
    public static function menu(User $user, array $destination): array
    {
        $voucher = VacuumVoucher::where('company_id', $destination['company_id'])
            ->whereKey($destination['voucher_id'])
            ->firstOrFail();
        $company = Company::findOrFail($voucher->company_id);
        $id = (int) $voucher->id;
        $companyId = (int) $voucher->company_id;
        $filters = ['company_id' => $companyId];
        $encryptedId = Crypt::encryptString((string) $id);

        $receiveAllowed = self::canAccess($user, $company, 'tree-cutting-receive');
        $sortingAllowed = self::canAccess($user, $company, 'casting-sorting');
        $hasReceive = $voucher->treeCuttingReceiveItems()
            ->where('company_id', $companyId)
            ->exists();
        $sortingEnabled = $sortingAllowed && $hasReceive;

        $actions = [
            [
                'key' => 'view_tree_cutting_receive_pdf',
                'label' => 'View Tree Cutting Receive PDF',
                'screen' => 'voucher_pdf',
                'enabled' => $receiveAllowed,
                'reason' => $receiveAllowed ? null : 'You do not have access to Tree Cutting Receive.',
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => $filters,
                'url' => $receiveAllowed ? url('/api/tree-cutting-receive/pdf/' . $id) . '?' . http_build_query($filters) : null,
                'web_url' => $receiveAllowed ? route('company.tree-cutting-receive.pdf', [$company->slug, $encryptedId]) : null,
            ],
            [
                'key' => 'tree_cutting_receive_list',
                'label' => 'Tree Cutting Receive List',
                'screen' => 'tree_cutting_receive_list',
                'enabled' => $receiveAllowed,
                'reason' => $receiveAllowed ? null : 'You do not have access to Tree Cutting Receive.',
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => $filters + ['voucher_id' => $id],
                'url' => $receiveAllowed ? url('/api/tree-cutting-receive') . '?' . http_build_query($filters + ['voucher_id' => $id]) : null,
                'web_url' => $receiveAllowed ? route('company.tree-cutting-receive.index', [$company->slug, 'voucher_id' => $id]) : null,
            ],
            [
                'key' => 'casting_sorting',
                'label' => 'Casting Sorting',
                'screen' => 'casting_sorting',
                'enabled' => $sortingEnabled,
                'reason' => !$sortingAllowed
                    ? 'You do not have access to Casting Sorting.'
                    : ($hasReceive ? null : 'Complete Tree Cutting Receive first.'),
                'method' => 'GET',
                'voucher_id' => $id,
                'filters' => $filters,
                'url' => $sortingEnabled ? url('/api/casting-sorting/' . $id) . '?' . http_build_query($filters) : null,
                'web_url' => $sortingEnabled ? route('company.casting-sorting.show', [$company->slug, $encryptedId]) : null,
            ],
        ];

        return array_merge($destination, [
            'action' => 'choose',
            'screen' => 'tree_cutting_receive_actions',
            'url' => null,
            'method' => 'GET',
            'requires_auth' => true,
            'filters' => ['company_id' => $companyId, 'can_manage' => $receiveAllowed],
            'actions' => $actions,
        ]);
    }

    private static function canAccess(User $user, Company $company, string $module): bool
    {
        $aliases = array_unique([$module, str_replace('-', '_', $module)]);
        $permissions = [];
        foreach ($aliases as $alias) {
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
