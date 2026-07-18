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
                ['name' => "voucher-history-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "voucher-history-{$action}",
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
                'voucher-history-view',
                'voucher-history-create',
                'voucher-history-edit',
                'voucher-history-delete',
                'voucher-history-manage',
            ])
            ->delete();
    }
};
