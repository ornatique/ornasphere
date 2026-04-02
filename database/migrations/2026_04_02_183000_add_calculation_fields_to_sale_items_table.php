<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'other_weight')) {
                $table->decimal('other_weight', 10, 3)->nullable()->after('gross_weight');
            }
            if (!Schema::hasColumn('sale_items', 'waste_percent')) {
                $table->decimal('waste_percent', 8, 3)->nullable()->after('purity');
            }
            if (!Schema::hasColumn('sale_items', 'net_purity')) {
                $table->decimal('net_purity', 8, 3)->nullable()->after('waste_percent');
            }
            if (!Schema::hasColumn('sale_items', 'metal_amount')) {
                $table->decimal('metal_amount', 12, 2)->nullable()->after('metal_rate');
            }
            if (!Schema::hasColumn('sale_items', 'labour_rate')) {
                $table->decimal('labour_rate', 12, 2)->nullable()->after('metal_amount');
            }
            if (!Schema::hasColumn('sale_items', 'approval_item_id')) {
                $table->unsignedBigInteger('approval_item_id')->nullable()->after('itemset_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            foreach (['approval_item_id','labour_rate','metal_amount','net_purity','waste_percent','other_weight'] as $column) {
                if (Schema::hasColumn('sale_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
