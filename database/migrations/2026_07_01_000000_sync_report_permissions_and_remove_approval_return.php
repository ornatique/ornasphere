<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $this->createPermissions([
            'sale-advance',
            'approval-return',
            'report-purchase-receiver-summary',
            'report-outstanding-amount',
        ]);

        $this->deletePermissions([
            'return',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $this->createPermissions([
            'return',
        ]);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function createPermissions(array $modules): void
    {
        $actions = ['view', 'create', 'edit', 'delete', 'manage'];

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $name = "{$module}-{$action}";

                DB::table('permissions')->updateOrInsert(
                    ['name' => $name, 'guard_name' => 'web'],
                    [
                        'company_id' => null,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function deletePermissions(array $modules): void
    {
        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where(function ($query) use ($modules) {
                foreach ($modules as $module) {
                    $query->orWhere('name', 'like', "{$module}-%");
                }
            })
            ->pluck('id');

        if ($permissionIds->isEmpty()) {
            return;
        }

        DB::table('role_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('model_has_permissions')
            ->whereIn('permission_id', $permissionIds)
            ->delete();

        DB::table('permissions')
            ->whereIn('id', $permissionIds)
            ->delete();
    }
};
