<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "report-worker-loss-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "report-worker-loss-{$action}",
                    'guard_name' => 'web',
                    'company_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'report-worker-loss-view',
                'report-worker-loss-create',
                'report-worker-loss-edit',
                'report-worker-loss-delete',
                'report-worker-loss-manage',
            ])
            ->delete();
    }
};
