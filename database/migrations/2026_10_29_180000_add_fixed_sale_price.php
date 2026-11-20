<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        foreach (['item_sets', 'sale_items', 'approval_items'] as $table) {
            if (Schema::hasColumn($table, 'sale_price')) {
                continue;
            }
            Schema::table($table, function (Blueprint $table) {
                $table->decimal('sale_price', 15, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['item_sets', 'sale_items', 'approval_items'] as $table) {
            Schema::table($table, fn (Blueprint $table) => $table->dropColumn('sale_price'));
        }
    }
};
