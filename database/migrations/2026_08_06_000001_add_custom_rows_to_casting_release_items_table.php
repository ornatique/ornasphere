<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('casting_release_items')) {
            return;
        }

        if (Schema::hasColumn('casting_release_items', 'vacuum_voucher_item_id')) {
            DB::statement('ALTER TABLE `casting_release_items` MODIFY `vacuum_voucher_item_id` BIGINT UNSIGNED NULL');
        }

        if (!Schema::hasColumn('casting_release_items', 'custom_buch_no')) {
            DB::statement('ALTER TABLE `casting_release_items` ADD `custom_buch_no` VARCHAR(100) NULL AFTER `vacuum_voucher_item_id`');
        }

        if (!Schema::hasColumn('casting_release_items', 'is_custom')) {
            DB::statement('ALTER TABLE `casting_release_items` ADD `is_custom` TINYINT(1) NOT NULL DEFAULT 0 AFTER `custom_buch_no`');
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'casting_release_items')
            ->where('index_name', 'casting_release_items_custom_index')
            ->exists();

        if (!$indexExists) {
            DB::statement('CREATE INDEX `casting_release_items_custom_index` ON `casting_release_items` (`company_id`, `vacuum_voucher_id`, `is_custom`)');
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('casting_release_items')) {
            return;
        }

        $indexExists = DB::table('information_schema.statistics')
            ->where('table_schema', DB::getDatabaseName())
            ->where('table_name', 'casting_release_items')
            ->where('index_name', 'casting_release_items_custom_index')
            ->exists();

        if ($indexExists) {
            DB::statement('DROP INDEX `casting_release_items_custom_index` ON `casting_release_items`');
        }

        if (Schema::hasColumn('casting_release_items', 'is_custom')) {
            DB::statement('ALTER TABLE `casting_release_items` DROP COLUMN `is_custom`');
        }

        if (Schema::hasColumn('casting_release_items', 'custom_buch_no')) {
            DB::statement('ALTER TABLE `casting_release_items` DROP COLUMN `custom_buch_no`');
        }
    }
};
