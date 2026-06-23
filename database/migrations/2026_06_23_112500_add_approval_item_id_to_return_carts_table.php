<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('return_carts')) {
            return;
        }

        if (Schema::hasColumn('return_carts', 'sale_item_id')) {
            DB::statement('ALTER TABLE return_carts MODIFY sale_item_id BIGINT UNSIGNED NULL');
        }

        Schema::table('return_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('return_carts', 'approval_item_id')) {
                $table->unsignedBigInteger('approval_item_id')->nullable()->after('sale_item_id');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('return_carts')) {
            return;
        }

        Schema::table('return_carts', function (Blueprint $table) {
            if (Schema::hasColumn('return_carts', 'approval_item_id')) {
                $table->dropColumn('approval_item_id');
            }
        });

        if (Schema::hasColumn('return_carts', 'sale_item_id')) {
            DB::statement('ALTER TABLE return_carts MODIFY sale_item_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
