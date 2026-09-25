<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vacuum_voucher_items', function (Blueprint $table) {
            if (!Schema::hasColumn('vacuum_voucher_items', 'item_name')) {
                $table->string('item_name')->nullable()->after('buch_no');
            }

            if (!Schema::hasColumn('vacuum_voucher_items', 'remarks')) {
                $table->text('remarks')->nullable()->after('silver_wt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vacuum_voucher_items', function (Blueprint $table) {
            if (Schema::hasColumn('vacuum_voucher_items', 'remarks')) {
                $table->dropColumn('remarks');
            }

            if (Schema::hasColumn('vacuum_voucher_items', 'item_name')) {
                $table->dropColumn('item_name');
            }
        });
    }
};
