<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('sale_carts')) {
            return;
        }

        if (Schema::hasColumn('sale_carts', 'itemset_id')) {
            DB::statement('ALTER TABLE sale_carts MODIFY itemset_id BIGINT UNSIGNED NULL');
        }

        Schema::table('sale_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_carts', 'approval_item_id')) {
                $table->unsignedBigInteger('approval_item_id')->nullable()->after('itemset_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('sale_carts')) {
            return;
        }

        Schema::table('sale_carts', function (Blueprint $table) {
            if (Schema::hasColumn('sale_carts', 'approval_item_id')) {
                $table->dropColumn('approval_item_id');
            }
        });

        if (Schema::hasColumn('sale_carts', 'itemset_id')) {
            DB::statement('ALTER TABLE sale_carts MODIFY itemset_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
