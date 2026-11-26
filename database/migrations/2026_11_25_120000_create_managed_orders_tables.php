<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('managed_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies');
            $t->foreignId('customer_id')->constrained('customers');
            $t->date('order_date');
            $t->text('remarks')->nullable();
            $t->timestamps();
            $t->index(['company_id', 'order_date']);
        });
        Schema::create('managed_order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained('companies');
            $t->foreignId('managed_order_id')->constrained('managed_orders')->cascadeOnDelete();
            $t->foreignId('item_set_id')->constrained('item_sets');
            $t->string('item_name');
            $t->string('huid')->nullable();
            $t->string('qr_code')->nullable();
            $t->unsignedInteger('quantity');
            $t->decimal('gross_weight', 15, 3)->default(0);
            $t->decimal('net_weight', 15, 3)->default(0);
            $t->timestamps();
            $t->unique(['managed_order_id', 'item_set_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('managed_order_items');
        Schema::dropIfExists('managed_orders');
    }
};
