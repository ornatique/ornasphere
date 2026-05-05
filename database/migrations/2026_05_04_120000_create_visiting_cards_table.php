<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('visiting_cards', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('uploaded_by')->nullable();
            $table->string('image_path', 500)->nullable();
            $table->string('name')->nullable();
            $table->string('mobile_no', 30)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 191)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('original_language', 50)->nullable();
            $table->longText('original_text')->nullable();
            $table->longText('english_text')->nullable();
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'created_at']);
            $table->index(['company_id', 'mobile_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('visiting_cards');
    }
};
