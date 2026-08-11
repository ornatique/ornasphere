<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            if (!Schema::hasColumn('jobwork_receive_items', 'other_charge_details')) {
                $table->text('other_charge_details')->nullable()->after('other_amt');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            if (Schema::hasColumn('jobwork_receive_items', 'other_charge_details')) {
                $table->dropColumn('other_charge_details');
            }
        });
    }
};
