<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_issue_items', function (Blueprint $table) {
            $table->decimal('purity', 8, 3)->default(0)->after('other_amt');
            $table->decimal('net_purity', 8, 3)->default(0)->after('purity');
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_issue_items', function (Blueprint $table) {
            $table->dropColumn(['purity', 'net_purity']);
        });
    }
};
