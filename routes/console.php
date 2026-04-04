<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('erp:sync-company-rbac {--company_id=} {--slug=}', function () {
    $companyId = $this->option('company_id');
    $slug = $this->option('slug');

    $query = Company::query();
    if ($companyId) {
        $query->where('id', $companyId);
    }
    if ($slug) {
        $query->where('slug', $slug);
    }

    $companies = $query->get();
    if ($companies->isEmpty()) {
        $this->error('No company found for given filters.');
        return;
    }

    $modules = [
        'user',
        'customer',
        'item',
        'item-set',
        'label-config',
        'label-print',
        'other-charge',
        'sale',
        'return',
        'approval',
        'role',
        'permission',
    ];

    $actions = ['view', 'create', 'edit', 'delete'];

    $roleMatrix = [
        'company_admin' => ['*'],
        'sales_user' => [
            'sale-view', 'sale-create', 'sale-edit',
            'approval-view', 'approval-create', 'approval-edit',
            'return-view', 'return-create', 'return-edit',
            'customer-view', 'customer-create', 'customer-edit',
            'label-print-view',
        ],
        'inventory_user' => [
            'item-view', 'item-create', 'item-edit',
            'item-set-view', 'item-set-create', 'item-set-edit',
            'label-config-view', 'label-config-create', 'label-config-edit',
            'label-print-view', 'label-print-create',
            'other-charge-view', 'other-charge-create', 'other-charge-edit',
        ],
    ];

    $guard = 'web';
    $totalPermissions = 0;
    $totalRoles = 0;
    $totalUsersMapped = 0;

    foreach ($companies as $company) {
        $this->line("Processing company #{$company->id} ({$company->name})");

        $permissionByName = [];
        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $name = "{$module}-{$action}";
                $permission = Permission::where('name', $name)
                    ->where('guard_name', $guard)
                    ->first();

                if (!$permission) {
                    $permission = Permission::create([
                        'company_id' => $company->id,
                        'name' => $name,
                        'guard_name' => $guard,
                    ]);
                }

                $permissionByName[$name] = $permission;
                $totalPermissions++;
            }

            $manageName = "{$module}-manage";
            $managePermission = Permission::where('name', $manageName)
                ->where('guard_name', $guard)
                ->first();

            if (!$managePermission) {
                $managePermission = Permission::create([
                    'company_id' => $company->id,
                    'name' => $manageName,
                    'guard_name' => $guard,
                ]);
            }

            $permissionByName[$manageName] = $managePermission;
            $totalPermissions++;
        }

        foreach ($roleMatrix as $roleName => $permissionNames) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', $guard)
                ->first();

            if (!$role) {
                $role = Role::create([
                    'company_id' => $company->id,
                    'name' => $roleName,
                    'guard_name' => $guard,
                ]);
            }
            $totalRoles++;

            if ($permissionNames === ['*']) {
                $permissionModels = collect($modules)->map(function ($module) use ($permissionByName) {
                    return $permissionByName["{$module}-manage"];
                })->values();
            } else {
                $permissionModels = collect($permissionNames)
                    ->map(fn($permissionName) => $permissionByName[$permissionName] ?? null)
                    ->filter()
                    ->values();
            }

            $role->syncPermissions($permissionModels);
        }

        $users = User::where('company_id', $company->id)->get();
        foreach ($users as $user) {
            $userRoleName = strtolower((string) $user->role);
            if ($userRoleName === '') {
                continue;
            }

            if ($userRoleName === 'employee' || $userRoleName === 'sales') {
                $targetRole = Role::where('name', 'sales_user')->where('guard_name', $guard)->first();
            } elseif ($userRoleName === 'inventory') {
                $targetRole = Role::where('name', 'inventory_user')->where('guard_name', $guard)->first();
            } elseif ($userRoleName === 'company_admin' || $userRoleName === 'admin') {
                $targetRole = Role::where('name', 'company_admin')->where('guard_name', $guard)->first();
            } else {
                $targetRole = Role::where('name', $user->role)->where('guard_name', $guard)->first();
            }

            if ($targetRole) {
                $user->syncRoles([$targetRole]);
                $totalUsersMapped++;
            }
        }

        $adminCount = User::where('company_id', $company->id)->role('company_admin')->count();
        if ($adminCount === 0) {
            $firstCompanyUser = User::where('company_id', $company->id)->orderBy('id')->first();
            if ($firstCompanyUser) {
                $adminRole = Role::where('name', 'company_admin')->where('guard_name', $guard)->first();
                if ($adminRole) {
                    $firstCompanyUser->syncRoles([$adminRole]);
                    $totalUsersMapped++;
                }
            }
        }
    }

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->info('RBAC sync completed.');
    $this->line("Permissions processed: {$totalPermissions}");
    $this->line("Roles processed: {$totalRoles}");
    $this->line("Users mapped: {$totalUsersMapped}");
})->purpose('Create/sync company roles, permissions and user-role mapping');
