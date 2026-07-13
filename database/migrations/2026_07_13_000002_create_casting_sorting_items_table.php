<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casting_sorting_items')) {
            Schema::create('casting_sorting_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
                $table->decimal('weight', 12, 3)->nullable();
                $table->unsignedInteger('quantity')->nullable();
                $table->foreignId('sorted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('sorted_at')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'vacuum_voucher_id']);
                $table->index(['company_id', 'item_id']);
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "casting-sorting-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "casting-sorting-{$action}",
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
        Schema::dropIfExists('casting_sorting_items');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'casting-sorting-view',
                'casting-sorting-create',
                'casting-sorting-edit',
                'casting-sorting-delete',
                'casting-sorting-manage',
            ])
            ->delete();
    }
};
