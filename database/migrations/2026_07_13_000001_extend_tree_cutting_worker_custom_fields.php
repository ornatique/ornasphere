<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE tree_cutting_issue_items MODIFY vacuum_voucher_item_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE tree_cutting_receive_items MODIFY vacuum_voucher_item_id BIGINT UNSIGNED NULL');

        Schema::table('tree_cutting_issue_items', function (Blueprint $table) {
            if (!Schema::hasColumn('tree_cutting_issue_items', 'job_worker_id')) {
                $table->foreignId('job_worker_id')->nullable()->after('vacuum_voucher_item_id')->constrained('job_workers')->nullOnDelete();
            }
            if (!Schema::hasColumn('tree_cutting_issue_items', 'custom_buch_no')) {
                $table->string('custom_buch_no')->nullable()->after('job_worker_id');
            }
            if (!Schema::hasColumn('tree_cutting_issue_items', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('custom_buch_no');
            }
        });

        Schema::table('tree_cutting_receive_items', function (Blueprint $table) {
            if (!Schema::hasColumn('tree_cutting_receive_items', 'tree_cutting_issue_item_id')) {
                $table->foreignId('tree_cutting_issue_item_id')->nullable()->after('vacuum_voucher_item_id')->constrained('tree_cutting_issue_items')->nullOnDelete();
            }
            if (!Schema::hasColumn('tree_cutting_receive_items', 'job_worker_id')) {
                $table->foreignId('job_worker_id')->nullable()->after('tree_cutting_issue_item_id')->constrained('job_workers')->nullOnDelete();
            }
            if (!Schema::hasColumn('tree_cutting_receive_items', 'custom_buch_no')) {
                $table->string('custom_buch_no')->nullable()->after('job_worker_id');
            }
            if (!Schema::hasColumn('tree_cutting_receive_items', 'is_custom')) {
                $table->boolean('is_custom')->default(false)->after('custom_buch_no');
            }
        });

        DB::statement('
            UPDATE tree_cutting_issue_items tci
            INNER JOIN vacuum_vouchers vv ON vv.id = tci.vacuum_voucher_id
            SET tci.job_worker_id = vv.job_worker_id
            WHERE tci.job_worker_id IS NULL
                AND tci.is_custom = 0
                AND vv.job_worker_id IS NOT NULL
        ');

        DB::statement('
            UPDATE tree_cutting_receive_items tcr
            INNER JOIN tree_cutting_issue_items tci
                ON tci.company_id = tcr.company_id
                AND tci.vacuum_voucher_id = tcr.vacuum_voucher_id
                AND tci.vacuum_voucher_item_id = tcr.vacuum_voucher_item_id
                AND tci.is_custom = 0
            SET tcr.tree_cutting_issue_item_id = tci.id,
                tcr.job_worker_id = tci.job_worker_id,
                tcr.custom_buch_no = tci.custom_buch_no,
                tcr.is_custom = tci.is_custom
            WHERE tcr.tree_cutting_issue_item_id IS NULL
        ');
    }

    public function down(): void
    {
        Schema::table('tree_cutting_receive_items', function (Blueprint $table) {
            if (Schema::hasColumn('tree_cutting_receive_items', 'is_custom')) {
                $table->dropColumn('is_custom');
            }
            if (Schema::hasColumn('tree_cutting_receive_items', 'custom_buch_no')) {
                $table->dropColumn('custom_buch_no');
            }
            if (Schema::hasColumn('tree_cutting_receive_items', 'job_worker_id')) {
                $table->dropConstrainedForeignId('job_worker_id');
            }
            if (Schema::hasColumn('tree_cutting_receive_items', 'tree_cutting_issue_item_id')) {
                $table->dropConstrainedForeignId('tree_cutting_issue_item_id');
            }
        });

        Schema::table('tree_cutting_issue_items', function (Blueprint $table) {
            if (Schema::hasColumn('tree_cutting_issue_items', 'is_custom')) {
                $table->dropColumn('is_custom');
            }
            if (Schema::hasColumn('tree_cutting_issue_items', 'custom_buch_no')) {
                $table->dropColumn('custom_buch_no');
            }
            if (Schema::hasColumn('tree_cutting_issue_items', 'job_worker_id')) {
                $table->dropConstrainedForeignId('job_worker_id');
            }
        });

        DB::statement('ALTER TABLE tree_cutting_receive_items MODIFY vacuum_voucher_item_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE tree_cutting_issue_items MODIFY vacuum_voucher_item_id BIGINT UNSIGNED NOT NULL');
    }
};
