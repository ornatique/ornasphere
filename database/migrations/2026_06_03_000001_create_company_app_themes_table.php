<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_app_themes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name')->default('Default Theme');
            $table->string('mode', 20)->default('normal');
            $table->boolean('is_active')->default(false);
            $table->string('primary_color', 20)->default('#000000');
            $table->string('secondary_color', 20)->default('#FFD700');
            $table->string('background_color', 20)->default('#FFFFFF');
            $table->string('text_color', 20)->default('#111111');
            $table->json('primary_gradient')->nullable();
            $table->json('secondary_gradient')->nullable();
            $table->json('background_gradient')->nullable();
            $table->json('text_gradient')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'is_active']);
        });

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->insertOrIgnore([
                'name' => "app-theme-{$action}",
                'guard_name' => 'web',
                'company_id' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_app_themes');

        $permissionIds = DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'app-theme-%')
            ->pluck('id');

        if ($permissionIds->isNotEmpty()) {
            DB::table('role_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
            DB::table('model_has_permissions')->whereIn('permission_id', $permissionIds)->delete();
        }

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->where('name', 'like', 'app-theme-%')
            ->delete();
    }
};
