<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('sale_return_items', 'approval_item_id')) {
            Schema::table('sale_return_items', function (Blueprint $table) {
                $table->unsignedBigInteger('approval_item_id')->nullable()->after('sale_item_id');
                $table->index('approval_item_id', 'sale_return_items_approval_item_id_index');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_return_items', 'approval_item_id')) {
            Schema::table('sale_return_items', function (Blueprint $table) {
                $table->dropIndex('sale_return_items_approval_item_id_index');
                $table->dropColumn('approval_item_id');
            });
        }
    }
};
