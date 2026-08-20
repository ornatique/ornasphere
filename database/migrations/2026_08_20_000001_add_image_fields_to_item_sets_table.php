<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            $table->string('image_disk')->nullable()->after('printed_at');
            $table->string('image_path')->nullable()->after('image_disk');
            $table->string('image_mime', 80)->nullable()->after('image_path');
            $table->unsignedInteger('image_size')->nullable()->after('image_mime');
            $table->timestamp('image_uploaded_at')->nullable()->after('image_size');
        });
    }

    public function down(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            $table->dropColumn([
                'image_disk',
                'image_path',
                'image_mime',
                'image_size',
                'image_uploaded_at',
            ]);
        });
    }
};
