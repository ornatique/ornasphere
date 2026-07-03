<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $removeNames = [
            'notification-create',
            'notification-edit',
            'notification-delete',
            'notification-manage',
        ];

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $removeNames)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $removeNames)
            ->delete();

        DB::table('permissions')->insertOrIgnore([[
            'name' => 'notification-view',
            'guard_name' => 'web',
            'company_id' => null,
            'created_at' => now(),
            'updated_at' => now(),
        ]]);
    }

    public function down(): void
    {
        //
    }
};
