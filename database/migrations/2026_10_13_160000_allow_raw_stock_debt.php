<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('jobwork_raw_stock_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_item_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('jobwork_raw_stock_allocations')->whereNull('voucher_item_id')->exists()) {
            throw new \RuntimeException('Reconcile raw stock debt before rolling back this migration.');
        }
        Schema::table('jobwork_raw_stock_allocations', function (Blueprint $table) {
            $table->unsignedBigInteger('voucher_item_id')->nullable(false)->change();
        });
    }
};
