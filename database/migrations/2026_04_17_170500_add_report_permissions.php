<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $modules = [
            'report-sales-summary',
            'report-stock-position',
            'report-approval-outstanding',
        ];

        $actions = ['view', 'create', 'edit', 'delete', 'manage'];
        $now = now();

        foreach ($modules as $module) {
            foreach ($actions as $action) {
                $name = "{$module}-{$action}";
                $exists = DB::table('permissions')
                    ->where('name', $name)
                    ->where('guard_name', 'web')
                    ->exists();

                if (!$exists) {
                    DB::table('permissions')->insert([
                        'name' => $name,
                        'guard_name' => 'web',
                        'company_id' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $modules = [
            'report-sales-summary',
            'report-stock-position',
            'report-approval-outstanding',
        ];

        foreach ($modules as $module) {
            DB::table('permissions')
                ->where('guard_name', 'web')
                ->where('name', 'like', $module . '-%')
                ->delete();
        }
    }
};

