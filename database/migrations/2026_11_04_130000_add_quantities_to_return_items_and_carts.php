<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('return_carts') && !Schema::hasColumn('return_carts', 'qty')) {
            Schema::table('return_carts', function (Blueprint $table) {
                $table->unsignedInteger('qty')->default(1)->after('approval_item_id');
            });
        }

        if (Schema::hasTable('sale_return_items') && !Schema::hasColumn('sale_return_items', 'qty')) {
            Schema::table('sale_return_items', function (Blueprint $table) {
                $table->unsignedInteger('qty')->default(1)->after('return_amount');
            });
        }

        if (Schema::hasTable('sale_return_items') && Schema::hasColumn('sale_return_items', 'qty')) {
            if (Schema::hasColumn('sale_return_items', 'sale_item_id') && Schema::hasTable('sale_items') && Schema::hasColumn('sale_items', 'qty')) {
                DB::statement('UPDATE sale_return_items sri JOIN sale_items si ON si.id = sri.sale_item_id SET sri.qty = GREATEST(1, COALESCE(si.qty, 1)) WHERE sri.qty = 1');
            }

            if (Schema::hasColumn('sale_return_items', 'approval_item_id') && Schema::hasTable('approval_items') && Schema::hasColumn('approval_items', 'qty')) {
                DB::statement('UPDATE sale_return_items sri JOIN approval_items ai ON ai.id = sri.approval_item_id SET sri.qty = GREATEST(1, COALESCE(ai.qty, 1)) WHERE sri.qty = 1');
            }
        }
    }

    public function down(): void
    {
        foreach (['sale_return_items', 'return_carts'] as $tableName) {
            if (Schema::hasTable($tableName) && Schema::hasColumn($tableName, 'qty')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropColumn('qty');
                });
            }
        }
    }
};
