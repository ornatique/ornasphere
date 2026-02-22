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
        Schema::create('metal_ledgers', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('sale_id');

            $table->unsignedBigInteger('customer_id');

            $table->enum('metal_type', ['gold', 'silver']);

            $table->decimal('weight', 10, 3);

            $table->enum('type', ['debit', 'credit']);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metal_ledgers');
    }
};
