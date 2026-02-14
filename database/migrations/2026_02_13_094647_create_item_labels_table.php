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
        Schema::create('item_labels', function (Blueprint $table) {

            $table->id();

            $table->foreignId('company_id')->constrained()->cascadeOnDelete();

            $table->foreignId('item_id')->constrained()->cascadeOnDelete();

            $table->foreignId('label_config_id')->constrained()->cascadeOnDelete();

            $table->string('qr_code')->unique();

            $table->string('barcode')->unique();

            $table->integer('serial_no');

            $table->boolean('is_printed')->default(0);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('item_labels');
    }
};
