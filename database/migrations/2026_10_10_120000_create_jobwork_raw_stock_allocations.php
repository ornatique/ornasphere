<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('jobwork_raw_stock_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id')->index();
            $table->foreignId('jobwork_issue_id')->constrained('jobwork_issues')->cascadeOnDelete();
            $table->foreignId('voucher_item_id')->constrained('customer_advance_voucher_items')->restrictOnDelete();
            $table->unsignedBigInteger('item_id');
            $table->decimal('net_weight', 15, 3);
            $table->unique(['jobwork_issue_id', 'voucher_item_id'], 'raw_issue_receipt_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobwork_raw_stock_allocations');
    }
};
