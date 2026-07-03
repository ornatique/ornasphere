<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [[
            'name' => 'notification-view',
            'guard_name' => 'web',
            'company_id' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]];

        DB::table('permissions')->insertOrIgnore($permissions);

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', array_column($permissions, 'name'))
            ->update([
                'company_id' => null,
                'updated_at' => $now,
            ]);
    }

    public function down(): void
    {
        $names = ['notification-view'];

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', $names)
            ->delete();
    }
};
