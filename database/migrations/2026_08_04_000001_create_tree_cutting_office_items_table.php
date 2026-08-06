<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tree_cutting_office_items')) {
            Schema::create('tree_cutting_office_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_item_id')->constrained('vacuum_voucher_items')->cascadeOnDelete();
                $table->decimal('tree_wt', 12, 3)->nullable();
                $table->decimal('office_cut_wt', 12, 3)->nullable();
                $table->decimal('remaining_tree_wt', 12, 3)->nullable();
                $table->string('issue_group_key', 64)->nullable()->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('office_cut_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'vacuum_voucher_item_id'], 'tree_cutting_office_company_item_unique');
                $table->index(['company_id', 'vacuum_voucher_id']);
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "tree-cutting-office-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "tree-cutting-office-{$action}",
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
        Schema::dropIfExists('tree_cutting_office_items');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'tree-cutting-office-view',
                'tree-cutting-office-create',
                'tree-cutting-office-edit',
                'tree-cutting-office-delete',
                'tree-cutting-office-manage',
            ])
            ->delete();
    }
};
