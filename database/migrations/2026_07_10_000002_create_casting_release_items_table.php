<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casting_release_items')) {
            Schema::create('casting_release_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_item_id')->constrained('vacuum_voucher_items')->cascadeOnDelete();
                $table->decimal('release_tree_wt', 12, 3)->nullable();
                $table->decimal('release_tree_bhuko', 12, 3)->nullable();
                $table->decimal('loss', 12, 3)->nullable();
                $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('released_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'vacuum_voucher_item_id'], 'casting_release_company_item_unique');
                $table->index(['company_id', 'vacuum_voucher_id']);
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "casting-release-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "casting-release-{$action}",
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
        Schema::dropIfExists('casting_release_items');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'casting-release-view',
                'casting-release-create',
                'casting-release-edit',
                'casting-release-delete',
                'casting-release-manage',
            ])
            ->delete();
    }
};
