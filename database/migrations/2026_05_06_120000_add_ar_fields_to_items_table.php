<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->string('jewellery_type', 30)->nullable()->after('item_group');
            $table->string('ar_mode', 30)->default('3d_view')->after('jewellery_type');
            $table->string('glb_url')->nullable()->after('ar_mode');
            $table->string('usdz_url')->nullable()->after('glb_url');
            $table->string('thumbnail_url')->nullable()->after('usdz_url');
            $table->string('deepar_effect_id')->nullable()->after('thumbnail_url');
            $table->json('ar_meta')->nullable()->after('deepar_effect_id');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn([
                'jewellery_type',
                'ar_mode',
                'glb_url',
                'usdz_url',
                'thumbnail_url',
                'deepar_effect_id',
                'ar_meta',
            ]);
        });
    }
};
