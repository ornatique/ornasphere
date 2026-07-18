<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

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
    }

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'vacuum-live-dashboard-view')
            ->delete();
    }
};
