<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('casting_metal_issue_items', function (Blueprint $table) {
            if (!Schema::hasColumn('casting_metal_issue_items', 'metal_weight')) {
                $table->decimal('metal_weight', 12, 3)->nullable()->after('other_metal');
            }
        });
    }

    public function down(): void
    {
        Schema::table('casting_metal_issue_items', function (Blueprint $table) {
            if (Schema::hasColumn('casting_metal_issue_items', 'metal_weight')) {
                $table->dropColumn('metal_weight');
            }
        });
    }
};
