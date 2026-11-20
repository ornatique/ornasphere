<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('item_sets') && !Schema::hasColumn('item_sets', 'quantity')) {
            Schema::table('item_sets', function (Blueprint $table) {
                $table->unsignedInteger('quantity')->default(1)->after('net_weight');
            });
        }

        if (Schema::hasTable('item_sets') && !Schema::hasColumn('item_sets', 'available_quantity')) {
            Schema::table('item_sets', function (Blueprint $table) {
                $table->unsignedInteger('available_quantity')->default(1)->after('quantity');
            });
        }

        if (Schema::hasTable('item_sets') && Schema::hasColumn('item_sets', 'quantity') && Schema::hasColumn('item_sets', 'available_quantity')) {
            DB::table('item_sets')->whereNull('quantity')->update(['quantity' => 1]);
            if (Schema::hasColumn('item_sets', 'is_sold')) {
                DB::statement('UPDATE item_sets SET available_quantity = CASE WHEN is_sold = 1 THEN 0 ELSE quantity END WHERE available_quantity IS NULL OR available_quantity = 1');
            }
        }

        if (Schema::hasTable('approval_items') && !Schema::hasColumn('approval_items', 'qty')) {
            Schema::table('approval_items', function (Blueprint $table) {
                $table->unsignedInteger('qty')->default(1)->after('item_id');
            });
        }

        foreach (['sale_carts', 'approval_carts'] as $cartTable) {
            if (Schema::hasTable($cartTable) && !Schema::hasColumn($cartTable, 'qty')) {
                Schema::table($cartTable, function (Blueprint $table) {
                    $table->unsignedInteger('qty')->default(1)->after('itemset_id');
                });
            }
        }
    }

    public function down(): void
    {
        foreach (['sale_carts', 'approval_carts', 'approval_items'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'qty')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('qty');
                });
            }
        }

        if (Schema::hasTable('item_sets')) {
            $columns = array_values(array_filter(['available_quantity', 'quantity'], fn ($column) => Schema::hasColumn('item_sets', $column)));
            if ($columns) {
                Schema::table('item_sets', fn (Blueprint $table) => $table->dropColumn($columns));
            }
        }
    }
};
