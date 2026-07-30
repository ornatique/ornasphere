<?php

use App\Models\Permission;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('customers')) {
            if (!Schema::hasColumn('customers', 'category_person_id')) {
                Schema::table('customers', function (Blueprint $table) {
                    $table->unsignedBigInteger('category_person_id')->nullable()->after('company_id');
                });
            }

            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->index('category_person_id', 'customers_category_person_id_index');
                });
            } catch (Throwable $e) {
            }
        }

        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            $permission = Permission::firstOrCreate([
                'name' => "person-{$action}",
                'guard_name' => 'web',
            ]);

            if ($permission->company_id !== null) {
                $permission->company_id = null;
                $permission->save();
            }
        }
    }

    public function down(): void
    {
        Permission::where('guard_name', 'web')
            ->whereIn('name', ['person-view', 'person-create', 'person-edit', 'person-delete', 'person-manage'])
            ->delete();

        if (Schema::hasTable('customers') && Schema::hasColumn('customers', 'category_person_id')) {
            try {
                Schema::table('customers', function (Blueprint $table) {
                    $table->dropIndex('customers_category_person_id_index');
                });
            } catch (Throwable $e) {
            }

            Schema::table('customers', function (Blueprint $table) {
                $table->dropColumn('category_person_id');
            });
        }
    }
};
