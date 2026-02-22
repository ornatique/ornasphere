<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_id');

            $table->unsignedBigInteger('product_id');

            $table->decimal('purity', 5, 3);

            $table->decimal('gross_weight', 10, 3);

            $table->decimal('net_weight', 10, 3);

            $table->decimal('fine_weight', 10, 3);

            $table->integer('qty')->default(1);

            $table->decimal('metal_rate', 12, 2)->default(0);

            $table->decimal('labour_amount', 12, 2)->default(0);

            $table->decimal('other_amount', 12, 2)->default(0);

            $table->decimal('total_amount', 12, 2)->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
