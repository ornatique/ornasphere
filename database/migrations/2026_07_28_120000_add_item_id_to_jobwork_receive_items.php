<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            if (!Schema::hasColumn('jobwork_receive_items', 'item_id')) {
                $table->foreignId('item_id')->nullable()->after('jobwork_issue_item_id')->constrained('items')->nullOnDelete();
            }
        });

        DB::statement('UPDATE jobwork_receive_items jri INNER JOIN jobwork_issue_items jii ON jii.id = jri.jobwork_issue_item_id SET jri.item_id = jii.item_id WHERE jri.item_id IS NULL');
        DB::statement('ALTER TABLE jobwork_receive_items MODIFY jobwork_issue_item_id BIGINT UNSIGNED NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE jobwork_receive_items MODIFY jobwork_issue_item_id BIGINT UNSIGNED NOT NULL');

        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            if (Schema::hasColumn('jobwork_receive_items', 'item_id')) {
                $table->dropConstrainedForeignId('item_id');
            }
        });
    }
};
