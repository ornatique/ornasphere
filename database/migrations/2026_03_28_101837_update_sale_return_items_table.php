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
        Schema::table('sale_return_items', function (Blueprint $table) {

            $table->unsignedBigInteger('sale_item_id')->nullable()->change(); // ✅ FIX

            $table->unsignedBigInteger('itemset_id')->nullable(); // 🔥 ADD
            $table->unsignedBigInteger('product_id')->nullable(); // 🔥 ADD
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
