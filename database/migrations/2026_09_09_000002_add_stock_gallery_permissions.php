<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            Permission::firstOrCreate([
                'name' => "stock-gallery-{$action}",
                'guard_name' => 'web',
            ], [
                'company_id' => null,
            ]);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', [
                'stock-gallery-view',
                'stock-gallery-create',
                'stock-gallery-edit',
                'stock-gallery-delete',
                'stock-gallery-manage',
            ])
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
