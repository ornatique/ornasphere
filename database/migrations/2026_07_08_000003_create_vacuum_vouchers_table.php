<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vacuum_vouchers')) {
            Schema::create('vacuum_vouchers', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->string('voucher_no', 50);
                $table->date('voucher_date');
                $table->foreignId('vacuum_process_id')->constrained('vacuum_processes')->cascadeOnDelete();
                $table->foreignId('job_worker_id')->constrained('job_workers')->cascadeOnDelete();
                $table->decimal('formula_value', 12, 3)->default(0);
                $table->decimal('gross_wt_total', 14, 3)->default(0);
                $table->decimal('buch_wt_total', 14, 3)->default(0);
                $table->decimal('net_wt_total', 14, 3)->default(0);
                $table->decimal('silver_wt_total', 14, 3)->default(0);
                $table->text('remarks')->nullable();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->unsignedInteger('modified_count')->default(0);
                $table->timestamps();

                $table->unique(['company_id', 'voucher_no']);
                $table->index(['company_id', 'voucher_date']);
            });
        }

        if (!Schema::hasTable('vacuum_voucher_items')) {
            Schema::create('vacuum_voucher_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('vacuum_voucher_id')->constrained('vacuum_vouchers')->cascadeOnDelete();
                $table->foreignId('vacuum_buch_id')->nullable()->constrained('vacuum_buchs')->nullOnDelete();
                $table->string('buch_no')->nullable();
                $table->decimal('gross_wt', 12, 3)->default(0);
                $table->decimal('buch_wt', 12, 3)->default(0);
                $table->decimal('net_wt', 12, 3)->default(0);
                $table->decimal('silver_wt', 12, 3)->default(0);
                $table->timestamps();
            });
        }

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                ['name' => "vacuum-voucher-{$action}", 'guard_name' => 'web'],
                [
                    'name' => "vacuum-voucher-{$action}",
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
        Schema::dropIfExists('vacuum_voucher_items');
        Schema::dropIfExists('vacuum_vouchers');

        DB::table('permissions')
            ->where('guard_name', 'web')
            ->whereIn('name', [
                'vacuum-voucher-view',
                'vacuum-voucher-create',
                'vacuum-voucher-edit',
                'vacuum-voucher-delete',
                'vacuum-voucher-manage',
            ])
            ->delete();
    }
};
