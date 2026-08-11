<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('casting_release_items') && !Schema::hasColumn('casting_release_items', 'custom_type')) {
            DB::statement("ALTER TABLE `casting_release_items` ADD `custom_type` VARCHAR(20) NULL AFTER `is_custom`");
            DB::statement("UPDATE `casting_release_items` SET `custom_type` = 'bhuko' WHERE `is_custom` = 1 AND `custom_type` IS NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('casting_release_items') && Schema::hasColumn('casting_release_items', 'custom_type')) {
            DB::statement("ALTER TABLE `casting_release_items` DROP COLUMN `custom_type`");
        }
    }
};
