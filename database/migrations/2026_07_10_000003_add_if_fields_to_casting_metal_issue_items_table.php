<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_metal_issue_items', function (Blueprint $table) {
            if (!Schema::hasColumn('casting_metal_issue_items', 'is_if')) {
                $table->boolean('is_if')->default(false)->after('issue_silver_wt');
            }

            if (!Schema::hasColumn('casting_metal_issue_items', 'pure_fine')) {
                $table->decimal('pure_fine', 12, 3)->nullable()->after('is_if');
            }

            if (!Schema::hasColumn('casting_metal_issue_items', 'if_percentage')) {
                $table->decimal('if_percentage', 5, 2)->nullable()->after('pure_fine');
            }

            if (!Schema::hasColumn('casting_metal_issue_items', 'other_metal')) {
                $table->decimal('other_metal', 12, 3)->nullable()->after('if_percentage');
            }
        });
    }

    public function down(): void
    {
        Schema::table('casting_metal_issue_items', function (Blueprint $table) {
            foreach (['other_metal', 'if_percentage', 'pure_fine', 'is_if'] as $column) {
                if (Schema::hasColumn('casting_metal_issue_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
