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
        Schema::table('sale_returns', function (Blueprint $table) {

            $table->string('source_type')->nullable(); // sale / approval
            $table->unsignedBigInteger('source_id')->nullable();
            $table->unsignedBigInteger('sale_id')->nullable()->change(); // IMPORTANT
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
