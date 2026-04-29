<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_items', 'remarks')) {
                $table->text('remarks')->nullable()->after('total_amount');
            }
        });

        Schema::table('approval_items', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_items', 'remarks')) {
                $table->text('remarks')->nullable()->after('status');
            }
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_return_items', 'remarks')) {
                $table->text('remarks')->nullable()->after('return_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_items', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });

        Schema::table('approval_items', function (Blueprint $table) {
            if (Schema::hasColumn('approval_items', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });

        Schema::table('sale_return_items', function (Blueprint $table) {
            if (Schema::hasColumn('sale_return_items', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }
};

