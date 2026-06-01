<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_advance_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->date('entry_date');
            $table->string('entry_type', 40);
            $table->string('payment_mode', 30)->nullable();
            $table->decimal('cash_in', 12, 2)->default(0);
            $table->decimal('cash_out', 12, 2)->default(0);
            $table->string('metal_type', 20)->nullable();
            $table->decimal('metal_in', 12, 3)->default(0);
            $table->decimal('metal_out', 12, 3)->default(0);
            $table->decimal('rate', 12, 2)->default(0);
            $table->string('reference_type', 30)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('remarks', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'customer_id', 'entry_date'], 'idx_adv_company_customer_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_advance_ledgers');
    }
};

