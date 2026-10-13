<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_carts', 'customer_id')) {
                $table->unsignedBigInteger('customer_id')->nullable()->after('company_id');
            }
        });

        if (!$this->indexExists('sale_carts', 'sale_carts_company_customer_idx')) {
            Schema::table('sale_carts', function (Blueprint $table) {
                $table->index(['company_id', 'customer_id'], 'sale_carts_company_customer_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('sale_carts', 'sale_carts_company_customer_idx')) {
            Schema::table('sale_carts', function (Blueprint $table) {
                $table->dropIndex('sale_carts_company_customer_idx');
            });
        }

        Schema::table('sale_carts', function (Blueprint $table) {
            if (Schema::hasColumn('sale_carts', 'customer_id')) {
                $table->dropColumn('customer_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
