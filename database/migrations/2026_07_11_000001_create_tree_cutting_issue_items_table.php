<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tree_cutting_issue_items')) {
            Schema::create('tree_cutting_issue_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_item_id')->constrained('vacuum_voucher_items')->cascadeOnDelete();
                $table->decimal('receive_tree_wt', 12, 3)->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'vacuum_voucher_item_id'], 'tree_cutting_issue_company_item_unique');
                $table->index(['company_id', 'vacuum_voucher_id']);
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "tree-cutting-issue-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "tree-cutting-issue-{$action}",
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
        Schema::dropIfExists('tree_cutting_issue_items');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'tree-cutting-issue-view',
                'tree-cutting-issue-create',
                'tree-cutting-issue-edit',
                'tree-cutting-issue-delete',
                'tree-cutting-issue-manage',
            ])
            ->delete();
    }
};
