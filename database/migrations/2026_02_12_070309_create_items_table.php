<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->onDelete('cascade');

            // Basic Details
            $table->string('item_name');
            $table->string('item_code')->unique();

            $table->string('metal')->nullable();
            $table->decimal('outward_carat', 8, 2)->nullable();
            $table->decimal('inward_carat', 8, 2)->nullable();
            $table->decimal('outward_purity', 8, 2)->nullable();
            $table->decimal('inward_purity', 8, 2)->nullable();

            $table->string('metal_formula')->nullable();
            $table->string('issue_type')->nullable();
            $table->string('jobwork_item_type')->nullable();

            $table->string('labour_type')->nullable();
            $table->decimal('labour_rate', 10, 2)->nullable();
            $table->string('labour_unit')->nullable();

            $table->string('tax_type')->nullable();
            $table->string('hsn')->nullable();
            $table->string('sac_code')->nullable();
            $table->string('export_hsn')->nullable();

            // Checkboxes
            $table->boolean('auto_load_purity')->default(false);
            $table->boolean('auto_create_label_purchase')->default(false);
            $table->boolean('auto_create_label_config')->default(false);
            $table->boolean('reuse')->default(false);

            $table->decimal('numeric_length', 8, 2)->nullable();
            $table->string('item_group')->nullable();
            $table->text('remarks')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('items');
    }
};
