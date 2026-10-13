<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('try_on_category', 20)->default('none');
        });
        Schema::create('stock_gallery_media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_set_id')->unique()->constrained('item_sets')->cascadeOnDelete();
            $table->string('source_disk')->nullable();
            $table->string('source_path')->nullable();
            $table->string('original_path')->nullable();
            $table->string('cutout_path')->nullable();
            $table->string('status', 20)->default('empty');
            $table->uuid('revision')->nullable();
            $table->string('error')->nullable();
            $table->boolean('approved')->default(false);
            $table->boolean('enabled')->default(false);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_gallery_media');
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('try_on_category'));
    }
};
