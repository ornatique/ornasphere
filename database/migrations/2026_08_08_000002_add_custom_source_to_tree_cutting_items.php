<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('tree_cutting_office_items')) {
            DB::statement("ALTER TABLE `tree_cutting_office_items` MODIFY `vacuum_voucher_item_id` BIGINT UNSIGNED NULL");

            if (!Schema::hasColumn('tree_cutting_office_items', 'casting_release_item_id')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` ADD `casting_release_item_id` BIGINT UNSIGNED NULL AFTER `vacuum_voucher_item_id`");
            }
            if (!Schema::hasColumn('tree_cutting_office_items', 'custom_buch_no')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` ADD `custom_buch_no` VARCHAR(100) NULL AFTER `casting_release_item_id`");
            }
            if (!Schema::hasColumn('tree_cutting_office_items', 'is_custom')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` ADD `is_custom` TINYINT(1) NOT NULL DEFAULT 0 AFTER `custom_buch_no`");
            }
            try {
                DB::statement("CREATE INDEX `tree_cutting_office_casting_release_index` ON `tree_cutting_office_items` (`company_id`, `casting_release_item_id`)");
            } catch (\Throwable $e) {
                // Index already exists on some live databases.
            }
        }

        if (Schema::hasTable('tree_cutting_issue_items') && !Schema::hasColumn('tree_cutting_issue_items', 'casting_release_item_id')) {
            DB::statement("ALTER TABLE `tree_cutting_issue_items` ADD `casting_release_item_id` BIGINT UNSIGNED NULL AFTER `vacuum_voucher_item_id`");
            try {
                DB::statement("CREATE INDEX `tree_cutting_issue_casting_release_index` ON `tree_cutting_issue_items` (`company_id`, `casting_release_item_id`)");
            } catch (\Throwable $e) {
                // Index already exists on some live databases.
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('tree_cutting_issue_items') && Schema::hasColumn('tree_cutting_issue_items', 'casting_release_item_id')) {
            DB::statement("ALTER TABLE `tree_cutting_issue_items` DROP COLUMN `casting_release_item_id`");
        }

        if (Schema::hasTable('tree_cutting_office_items')) {
            if (Schema::hasColumn('tree_cutting_office_items', 'is_custom')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` DROP COLUMN `is_custom`");
            }
            if (Schema::hasColumn('tree_cutting_office_items', 'custom_buch_no')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` DROP COLUMN `custom_buch_no`");
            }
            if (Schema::hasColumn('tree_cutting_office_items', 'casting_release_item_id')) {
                DB::statement("ALTER TABLE `tree_cutting_office_items` DROP COLUMN `casting_release_item_id`");
            }
        }
    }
};
