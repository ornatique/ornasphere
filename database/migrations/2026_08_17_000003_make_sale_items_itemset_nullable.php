<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('sale_items', 'itemset_id')) {
            DB::statement('ALTER TABLE sale_items MODIFY itemset_id BIGINT UNSIGNED NULL');
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('sale_items', 'itemset_id')) {
            DB::statement('ALTER TABLE sale_items MODIFY itemset_id BIGINT UNSIGNED NOT NULL');
        }
    }
};
