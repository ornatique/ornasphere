<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $rolePivot = config('permission.table_names.role_has_permissions', 'role_has_permissions');
        $modelPivot = config('permission.table_names.model_has_permissions', 'model_has_permissions');

        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            $customerPermission = Permission::where('guard_name', 'web')
                ->where('name', "customer-{$action}")
                ->first();
            $personPermission = Permission::firstOrCreate([
                'name' => "person-{$action}",
                'guard_name' => 'web',
            ]);

            if (!$customerPermission) {
                continue;
            }

            $roleRows = DB::table($rolePivot)
                ->where('permission_id', $customerPermission->id)
                ->get();

            foreach ($roleRows as $row) {
                DB::table($rolePivot)->updateOrInsert([
                    'permission_id' => $personPermission->id,
                    'role_id' => $row->role_id,
                ], []);
            }

            $modelRows = DB::table($modelPivot)
                ->where('permission_id', $customerPermission->id)
                ->get();

            foreach ($modelRows as $row) {
                DB::table($modelPivot)->updateOrInsert([
                    'permission_id' => $personPermission->id,
                    'model_type' => $row->model_type,
                    'model_id' => $row->model_id,
                ], []);
            }
        }
    }

    public function down(): void
    {
    }
};
