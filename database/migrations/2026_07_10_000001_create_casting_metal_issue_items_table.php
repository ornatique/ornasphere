<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casting_metal_issue_items')) {
            Schema::create('casting_metal_issue_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_item_id')->constrained('vacuum_voucher_items')->cascadeOnDelete();
                $table->decimal('issue_silver_wt', 12, 3)->nullable();
                $table->text('remarks')->nullable();
                $table->foreignId('issued_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('issued_at')->nullable();
                $table->timestamps();

                $table->unique(['company_id', 'vacuum_voucher_item_id'], 'casting_metal_issue_company_item_unique');
                $table->index(['company_id', 'vacuum_voucher_id']);
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "casting-metal-issue-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "casting-metal-issue-{$action}",
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
        Schema::dropIfExists('casting_metal_issue_items');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'casting-metal-issue-view',
                'casting-metal-issue-create',
                'casting-metal-issue-edit',
                'casting-metal-issue-delete',
                'casting-metal-issue-manage',
            ])
            ->delete();
    }
};
