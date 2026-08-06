<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tree_cutting_office_items') && !Schema::hasColumn('tree_cutting_office_items', 'issue_group_key')) {
            Schema::table('tree_cutting_office_items', function (Blueprint $table) {
                $table->string('issue_group_key', 64)->nullable()->after('remaining_tree_wt')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tree_cutting_office_items') && Schema::hasColumn('tree_cutting_office_items', 'issue_group_key')) {
            Schema::table('tree_cutting_office_items', function (Blueprint $table) {
                $table->dropIndex(['issue_group_key']);
                $table->dropColumn('issue_group_key');
            });
        }
    }
};
