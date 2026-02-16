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
        Schema::create('item_sets', function (Blueprint $table) {

            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->decimal('gross_weight', 10, 3)->nullable();

            $table->string('other')->nullable();

            $table->decimal('net_weight', 10, 3)->nullable();
            $table->string('sale_labour_formula')->nullable();

            $table->decimal('sale_labour_rate', 10, 2)->nullable();
            $table->decimal('sale_labour_amount', 10, 2)->nullable();

            $table->string('sale_other')->nullable();

            $table->string('supplier_person')->nullable();

            $table->string('size')->nullable();
            $table->string('HUID')->nullable();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_sets');
    }
};
