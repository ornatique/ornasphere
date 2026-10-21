<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        // Foreign-key cascades remove obsolete role/user assignments as well.
        Permission::where('guard_name', 'web')->whereIn('name', [
            'gst-search-create', 'gst-search-edit', 'gst-search-delete', 'gst-search-manage',
        ])->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Obsolete permissions and their prior assignments are intentionally not recreated.
    }
};
