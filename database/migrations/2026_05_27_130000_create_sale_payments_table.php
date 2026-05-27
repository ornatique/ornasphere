<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->foreignId('sale_id')->constrained('sales')->cascadeOnDelete();
            $table->decimal('amount', 12, 2)->default(0);
            $table->date('paid_on');
            $table->string('payment_mode', 30)->nullable();
            $table->string('payment_reference', 120)->nullable();
            $table->string('payment_note', 255)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['sale_id', 'paid_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_payments');
    }
};
