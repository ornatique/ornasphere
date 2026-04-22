<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_issue_items', function (Blueprint $table) {
            $table->decimal('other_amt', 12, 2)->default(0)->after('other_wt');
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_issue_items', function (Blueprint $table) {
            $table->dropColumn('other_amt');
        });
    }
};

