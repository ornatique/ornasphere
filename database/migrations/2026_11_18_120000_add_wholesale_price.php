<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->boolean('wholesale_price_enabled')->default(false);
        });
        Schema::table('item_sets', function (Blueprint $table) {
            $table->decimal('wholesale_price', 15, 2)->nullable()->after('sale_price');
        });
    }

    public function down(): void
    {
        Schema::table('item_sets', fn (Blueprint $table) => $table->dropColumn('wholesale_price'));
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn('wholesale_price_enabled'));
    }
};
