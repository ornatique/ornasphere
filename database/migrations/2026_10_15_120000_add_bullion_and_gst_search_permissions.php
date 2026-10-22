<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        foreach (['bullion-view', 'bullion-create', 'gst-search-view'] as $name) {
            Permission::firstOrCreate(['name'=>$name, 'guard_name'=>'web'], ['company_id'=>null]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')->whereIn('name', ['bullion-view','bullion-create','gst-search-view'])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
