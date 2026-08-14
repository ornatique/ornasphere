<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_advance_voucher_items', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_advance_voucher_items', 'metal_type')) {
                $table->string('metal_type', 20)->nullable()->after('item_name');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_advance_voucher_items', function (Blueprint $table) {
            if (Schema::hasColumn('customer_advance_voucher_items', 'metal_type')) {
                $table->dropColumn('metal_type');
            }
        });
    }
};
