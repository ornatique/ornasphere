<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_carts', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('itemset_id');

            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('company_id')->references('id')->on('companies')->onDelete('cascade');
            $table->foreign('itemset_id')->references('id')->on('itemsets')->onDelete('cascade');

            // Prevent duplicate scans
            $table->unique(['user_id', 'itemset_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_carts');
    }
};
