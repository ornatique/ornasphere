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
        Schema::create('label_configs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('company_id')->constrained()->onDelete('cascade');
            $table->foreignId('item_id')->constrained()->onDelete('cascade');

            $table->string('prefix')->nullable();
            $table->integer('numeric_length')->nullable();
            $table->bigInteger('last_no')->default(0);

            $table->boolean('reuse')->default(false);
            $table->boolean('random')->default(false);

            $table->bigInteger('min_no')->nullable();
            $table->bigInteger('max_no')->nullable();

            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('label_configs');
    }
};
