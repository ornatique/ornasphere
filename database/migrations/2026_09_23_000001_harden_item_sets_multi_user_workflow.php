<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('item_sets', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('item_sets', 'updated_by')) {
                $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
            }
        });

        Schema::table('item_sets', function (Blueprint $table) {
            if (Schema::hasColumn('item_sets', 'qr_code')) {
                $table->unique(['company_id', 'qr_code'], 'item_sets_company_qr_unique');
            }

            if (Schema::hasColumn('item_sets', 'barcode')) {
                $table->unique(['company_id', 'barcode'], 'item_sets_company_barcode_unique');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            if (Schema::hasColumn('item_sets', 'barcode')) {
                $table->dropUnique('item_sets_company_barcode_unique');
            }

            if (Schema::hasColumn('item_sets', 'qr_code')) {
                $table->dropUnique('item_sets_company_qr_unique');
            }
        });

        Schema::table('item_sets', function (Blueprint $table) {
            if (Schema::hasColumn('item_sets', 'updated_by')) {
                $table->dropConstrainedForeignId('updated_by');
            }

            if (Schema::hasColumn('item_sets', 'created_by')) {
                $table->dropConstrainedForeignId('created_by');
            }
        });
    }
};
