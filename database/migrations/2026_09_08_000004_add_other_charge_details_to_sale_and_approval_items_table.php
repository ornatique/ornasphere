<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'other_charge_details')) {
                $table->text('other_charge_details')->nullable()->after('other_amount');
            }
        });

        Schema::table('approval_items', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_items', 'other_charge_details')) {
                $table->text('other_charge_details')->nullable()->after('other_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'other_charge_details')) {
                $table->dropColumn('other_charge_details');
            }
        });

        Schema::table('approval_items', function (Blueprint $table) {
            if (Schema::hasColumn('approval_items', 'other_charge_details')) {
                $table->dropColumn('other_charge_details');
            }
        });
    }
};
