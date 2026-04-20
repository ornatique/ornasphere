<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $module = 'report-barcode-history';
        $actions = ['view', 'create', 'edit', 'delete', 'manage'];
        $now = now();

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

    public function down(): void
    {
        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'report-barcode-history-%')
            ->delete();
    }
};

