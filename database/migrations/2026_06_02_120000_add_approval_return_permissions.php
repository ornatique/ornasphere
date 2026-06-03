<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $actions = ['view', 'create', 'edit', 'delete', 'manage'];

        foreach ($actions as $action) {
            $oldName = "return-{$action}";
            $newName = "approval-return-{$action}";

            DB::table('permissions')->insertOrIgnore([
                'name' => $newName,
                'guard_name' => 'web',
                'company_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $oldPermissionId = DB::table('permissions')
                ->where('name', $oldName)
                ->where('guard_name', 'web')
                ->value('id');

            $newPermissionId = DB::table('permissions')
                ->where('name', $newName)
                ->where('guard_name', 'web')
                ->value('id');

            if (!$oldPermissionId || !$newPermissionId) {
                continue;
            }

            DB::table('role_has_permissions')
                ->where('permission_id', $oldPermissionId)
                ->get()
                ->each(function ($row) use ($newPermissionId) {
                    DB::table('role_has_permissions')->insertOrIgnore([
                        'permission_id' => $newPermissionId,
                        'role_id' => $row->role_id,
                    ]);
                });

            DB::table('model_has_permissions')
                ->where('permission_id', $oldPermissionId)
                ->get()
                ->each(function ($row) use ($newPermissionId) {
                    DB::table('model_has_permissions')->insertOrIgnore([
                        'permission_id' => $newPermissionId,
                        'model_type' => $row->model_type,
                        'model_id' => $row->model_id,
                    ]);
                });
        }

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'approval-return-%')
            ->delete();

        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
