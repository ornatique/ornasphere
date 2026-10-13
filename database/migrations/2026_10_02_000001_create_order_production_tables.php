<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('customer_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->string('number')->unique();
            $t->date('order_date'); $t->date('delivery_date')->nullable();
            $t->string('customer_reference', 255)->nullable(); $t->text('remarks')->nullable();
            $t->string('status', 24)->default('draft');
            $t->unsignedBigInteger('created_by'); $t->timestamps();
            $t->index(['company_id', 'status']);
        });
        Schema::create('customer_order_lines', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('item_id')->constrained()->restrictOnDelete();
            $t->string('item_name', 255); $t->string('metal', 20); $t->decimal('purity', 7, 3);
            $t->unsignedInteger('pieces'); $t->decimal('expected_weight', 14, 3);
            $t->string('size', 255)->nullable(); $t->text('instructions')->nullable(); $t->timestamps();
        });
        Schema::create('cad_designs', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_order_line_id')->constrained()->restrictOnDelete();
            $t->unsignedInteger('revision'); $t->string('number')->unique();
            $t->foreignId('designer_id')->constrained('users')->restrictOnDelete();
            $t->date('due_date')->nullable(); $t->decimal('weight_per_piece', 14, 3)->nullable();
            $t->text('notes')->nullable(); $t->string('status', 24)->default('assigned');
            $t->string('approved_by', 255)->nullable(); $t->timestamp('approved_at')->nullable();
            $t->unsignedBigInteger('approval_recorded_by')->nullable(); $t->text('approval_remarks')->nullable();
            $t->timestamps(); $t->unique(['customer_order_line_id', 'revision']);
        });
        Schema::create('rpt_batches', function (Blueprint $t) {
            $t->id(); $t->foreignId('cad_design_id')->constrained()->restrictOnDelete();
            $t->string('number')->unique(); $t->foreignId('operator_id')->constrained('users')->restrictOnDelete();
            $t->string('printer', 255); $t->string('material', 255); $t->unsignedInteger('planned_pieces');
            $t->unsignedInteger('printed_pieces')->default(0); $t->unsignedInteger('accepted_pieces')->default(0);
            $t->unsignedInteger('rejected_pieces')->default(0); $t->decimal('pattern_weight', 14, 3)->default(0);
            $t->date('started_on')->nullable(); $t->date('completed_on')->nullable();
            $t->text('rejection_reason')->nullable(); $t->text('inspection_notes')->nullable();
            $t->string('status', 24)->default('planned'); $t->timestamps();
        });
        Schema::create('order_production_files', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('customer_order_line_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('cad_design_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('rpt_batch_id')->nullable()->constrained()->restrictOnDelete();
            $t->string('kind', 20); $t->string('name', 255); $t->string('path', 255); $t->timestamps();
        });
        Schema::create('order_production_events', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('user_id'); $t->string('description'); $t->timestamps();
        });
        Schema::create('customer_order_vouchers', function (Blueprint $t) {
            $t->id(); $t->foreignId('customer_order_id')->constrained()->restrictOnDelete();
            $t->foreignId('voucher_id')->unique()->constrained('customer_advance_vouchers')->restrictOnDelete();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['customer_order_vouchers', 'order_production_events', 'order_production_files', 'rpt_batches', 'cad_designs', 'customer_order_lines', 'customer_orders'] as $table) Schema::dropIfExists($table);
    }
};
