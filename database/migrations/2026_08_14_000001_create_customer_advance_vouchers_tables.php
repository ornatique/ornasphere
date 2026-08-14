<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advance_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('ledger_id')->nullable()->constrained('customer_advance_ledgers')->nullOnDelete();
            $table->string('voucher_no')->unique();
            $table->date('voucher_date');
            $table->string('entry_type', 40);
            $table->string('payment_mode', 30)->nullable();
            $table->decimal('amount', 12, 2)->default(0);
            $table->decimal('cash_in', 12, 2)->default(0);
            $table->decimal('cash_out', 12, 2)->default(0);
            $table->string('metal_type', 20)->nullable();
            $table->decimal('metal_in', 12, 3)->default(0);
            $table->decimal('metal_out', 12, 3)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'customer_id', 'voucher_date'], 'idx_adv_voucher_company_customer_date');
        });

        Schema::create('customer_advance_voucher_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained('customer_advance_vouchers')->cascadeOnDelete();
            $table->foreignId('itemset_id')->nullable()->constrained('item_sets')->nullOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('items')->nullOnDelete();
            $table->string('label_code')->nullable();
            $table->string('huid')->nullable();
            $table->string('item_name')->nullable();
            $table->string('metal_type', 20)->nullable();
            $table->decimal('gross_weight', 12, 3)->default(0);
            $table->decimal('other_weight', 12, 3)->default(0);
            $table->decimal('net_weight', 12, 3)->default(0);
            $table->decimal('purity', 8, 3)->default(0);
            $table->decimal('waste_percent', 8, 3)->default(0);
            $table->decimal('net_purity', 8, 3)->default(0);
            $table->decimal('fine_weight', 12, 3)->default(0);
            $table->decimal('metal_rate', 12, 2)->default(0);
            $table->boolean('apply_metal')->default(true);
            $table->decimal('metal_amount', 12, 2)->default(0);
            $table->decimal('labour_rate', 12, 2)->default(0);
            $table->boolean('apply_labour')->default(true);
            $table->decimal('labour_amount', 12, 2)->default(0);
            $table->decimal('other_amount', 12, 2)->default(0);
            $table->decimal('total_amount', 12, 2)->default(0);
            $table->string('remarks', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advance_voucher_items');
        Schema::dropIfExists('customer_advance_vouchers');
    }
};
