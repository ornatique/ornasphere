<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tree_cutting_issue_items') && !Schema::hasColumn('tree_cutting_issue_items', 'issue_group_key')) {
            Schema::table('tree_cutting_issue_items', function (Blueprint $table) {
                $table->string('issue_group_key', 64)->nullable()->after('job_worker_id')->index();
            });
        }

        if (Schema::hasTable('tree_cutting_receive_items') && !Schema::hasColumn('tree_cutting_receive_items', 'issue_group_key')) {
            Schema::table('tree_cutting_receive_items', function (Blueprint $table) {
                $table->string('issue_group_key', 64)->nullable()->after('tree_cutting_issue_item_id')->index();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tree_cutting_receive_items') && Schema::hasColumn('tree_cutting_receive_items', 'issue_group_key')) {
            Schema::table('tree_cutting_receive_items', function (Blueprint $table) {
                $table->dropIndex(['issue_group_key']);
                $table->dropColumn('issue_group_key');
            });
        }

        if (Schema::hasTable('tree_cutting_issue_items') && Schema::hasColumn('tree_cutting_issue_items', 'issue_group_key')) {
            Schema::table('tree_cutting_issue_items', function (Blueprint $table) {
                $table->dropIndex(['issue_group_key']);
                $table->dropColumn('issue_group_key');
            });
        }
    }
};
