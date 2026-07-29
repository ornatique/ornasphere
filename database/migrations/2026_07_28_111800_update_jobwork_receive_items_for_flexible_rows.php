<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            $table->dropUnique('jwr_item_unique');
            $table->decimal('other_wt', 12, 3)->default(0)->after('receive_gross_wt');
            $table->decimal('other_amt', 12, 2)->default(0)->after('other_wt');
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            $table->dropColumn(['other_wt', 'other_amt']);
            $table->unique(['jobwork_receive_id', 'jobwork_issue_item_id'], 'jwr_item_unique');
        });
    }
};
