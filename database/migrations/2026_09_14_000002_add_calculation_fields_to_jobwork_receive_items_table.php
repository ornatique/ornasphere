<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            if (!Schema::hasColumn('jobwork_receive_items', 'purity')) {
                $table->decimal('purity', 8, 3)->default(0)->after('other_amt');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'waste_percent')) {
                $table->decimal('waste_percent', 8, 3)->default(0)->after('purity');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'net_purity')) {
                $table->decimal('net_purity', 8, 3)->default(0)->after('waste_percent');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'metal_rate')) {
                $table->decimal('metal_rate', 12, 2)->default(0)->after('receive_fine_wt');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'metal_amount')) {
                $table->decimal('metal_amount', 12, 2)->default(0)->after('metal_rate');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'labour_rate')) {
                $table->decimal('labour_rate', 12, 2)->default(0)->after('metal_amount');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'labour_amount')) {
                $table->decimal('labour_amount', 12, 2)->default(0)->after('labour_rate');
            }

            if (!Schema::hasColumn('jobwork_receive_items', 'total_amount')) {
                $table->decimal('total_amount', 12, 2)->default(0)->after('labour_amount');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_receive_items', function (Blueprint $table) {
            foreach ([
                'total_amount',
                'labour_amount',
                'labour_rate',
                'metal_amount',
                'metal_rate',
                'net_purity',
                'waste_percent',
                'purity',
            ] as $column) {
                if (Schema::hasColumn('jobwork_receive_items', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
