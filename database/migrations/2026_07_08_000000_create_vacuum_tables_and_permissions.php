<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $permissions = [];

        foreach (['vacuum-buch', 'vacuum-process'] as $module) {
            foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
                $permissions[] = [
                    'name' => "{$module}-{$action}",
                    'guard_name' => 'web',
                    'company_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => $permission['name'],
                    'guard_name' => $permission['guard_name'],
                ],
                $permission
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', [
                'vacuum-buch-view',
                'vacuum-buch-create',
                'vacuum-buch-edit',
                'vacuum-buch-delete',
                'vacuum-buch-manage',
                'vacuum-process-view',
                'vacuum-process-create',
                'vacuum-process-edit',
                'vacuum-process-delete',
                'vacuum-process-manage',
            ])
            ->where('guard_name', 'web')
            ->delete();
    }
};
