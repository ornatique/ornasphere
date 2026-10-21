<?php
use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;
return new class extends Migration {
    public function up(): void {
        Permission::firstOrCreate(['name'=>'report-approval-summary-view','guard_name'=>'web'],['company_id'=>null]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
    public function down(): void {
        Permission::where('name','report-approval-summary-view')->where('guard_name','web')->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
