<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->updateOrInsert(
            ['name' => 'vacuum-live-dashboard-view', 'guard_name' => 'web'],
            [
                'name' => 'vacuum-live-dashboard-view',
                'guard_name' => 'web',
                'company_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $viewPermissionId = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'vacuum-live-dashboard-view')
            ->value('id');

        $writePermissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'vacuum-live-dashboard-create',
                'vacuum-live-dashboard-edit',
                'vacuum-live-dashboard-delete',
                'vacuum-live-dashboard-manage',
            ])
            ->pluck('id');

        if ($viewPermissionId && $writePermissionIds->isNotEmpty()) {
            $roleIds = DB::table('role_has_permissions')
                ->whereIn('permission_id', $writePermissionIds)
                ->pluck('role_id')
                ->unique();

            foreach ($roleIds as $roleId) {
                DB::table('role_has_permissions')->insertOrIgnore([
                    'permission_id' => $viewPermissionId,
                    'role_id' => $roleId,
                ]);
            }

            $modelPermissions = DB::table('model_has_permissions')
                ->whereIn('permission_id', $writePermissionIds)
                ->get(['model_type', 'model_id']);

            foreach ($modelPermissions as $modelPermission) {
                DB::table('model_has_permissions')->insertOrIgnore([
                    'permission_id' => $viewPermissionId,
                    'model_type' => $modelPermission->model_type,
                    'model_id' => $modelPermission->model_id,
                ]);
            }

            DB::table('role_has_permissions')->whereIn('permission_id', $writePermissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $writePermissionIds)->delete();
        }

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'vacuum-live-dashboard-create',
                'vacuum-live-dashboard-edit',
                'vacuum-live-dashboard-delete',
                'vacuum-live-dashboard-manage',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        $now = now();

        foreach (['create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "vacuum-live-dashboard-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "vacuum-live-dashboard-{$action}",
                    'guard_name' => 'web',
                    'company_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
