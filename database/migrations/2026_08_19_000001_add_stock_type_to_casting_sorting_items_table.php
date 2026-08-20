<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_sorting_items', function (Blueprint $table) {
            if (!Schema::hasColumn('casting_sorting_items', 'stock_type')) {
                $table->string('stock_type', 50)->default('raw_material')->after('item_id');
                $table->index(['company_id', 'stock_type']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('casting_sorting_items', function (Blueprint $table) {
            if (Schema::hasColumn('casting_sorting_items', 'stock_type')) {
                $table->dropIndex(['company_id', 'stock_type']);
                $table->dropColumn('stock_type');
            }
        });
    }
};
