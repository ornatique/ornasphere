<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('other_charges', function (Blueprint $table) {

            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            // Basic
            $table->string('other_charge');
            $table->string('code')->nullable();
  
            // Amount / Weight
            $table->decimal('default_amount', 12,2)->nullable();
            $table->decimal('default_weight', 12,3)->nullable();
            $table->integer('quantity_pcs')->nullable();

            // Weight formula
            $table->string('weight_formula')->nullable();
            $table->decimal('weight_percent', 8,2)->nullable();
            $table->decimal('sale_weight_percent', 8,2)->nullable();
            $table->decimal('purchase_weight_percent', 8,2)->nullable();

            // Other amount formula
            $table->string('other_amt_formula')->nullable();

            // Sequence
            $table->integer('sequence_no')->nullable();

            // Flags
            $table->boolean('is_default')->default(false);
            $table->boolean('is_selected')->default(false);
            $table->boolean('other_charge_ol')->default(false);

            // Purity
            $table->decimal('purity', 8,3)->nullable();
            $table->decimal('required_purity', 8,3)->nullable();

            // Merge
            $table->string('merge_other_charge')->nullable();
            $table->string('wt_operation')->nullable();

            // Options
            $table->boolean('carat_weight_auto_conversion')->default(false);
            $table->boolean('diamond')->default(false);
            $table->boolean('stone')->default(false);
            $table->boolean('stock_effect')->default(false);
            $table->boolean('party_account_effect')->default(false);

            // Item relation optional
            $table->foreignId('item_id')->nullable()->constrained()->nullOnDelete();

            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('other_charges');
    }
};

