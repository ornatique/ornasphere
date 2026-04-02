<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_items', function (Blueprint $table) {
            $table->unsignedBigInteger('itemset_id')->nullable()->after('approval_id');
            $table->string('huid')->nullable()->after('item_id');
            $table->decimal('other_weight', 10, 3)->nullable()->after('gross_weight');
            $table->decimal('purity', 8, 3)->nullable()->after('net_weight');
            $table->decimal('waste_percent', 8, 3)->nullable()->after('purity');
            $table->decimal('net_purity', 8, 3)->nullable()->after('waste_percent');
            $table->decimal('total_fine_weight', 12, 3)->nullable()->after('net_purity');
            $table->decimal('metal_rate', 12, 2)->nullable()->after('total_fine_weight');
            $table->decimal('metal_amount', 12, 2)->nullable()->after('metal_rate');
            $table->decimal('labour_rate', 12, 2)->nullable()->after('metal_amount');
            $table->decimal('labour_amount', 12, 2)->nullable()->after('labour_rate');
            $table->decimal('other_amount', 12, 2)->nullable()->after('labour_amount');
            $table->decimal('total_amount', 12, 2)->nullable()->after('other_amount');

            $table->foreign('itemset_id')->references('id')->on('item_sets')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('approval_items', function (Blueprint $table) {
            $table->dropForeign(['itemset_id']);
            $table->dropColumn([
                'itemset_id',
                'huid',
                'other_weight',
                'purity',
                'waste_percent',
                'net_purity',
                'total_fine_weight',
                'metal_rate',
                'metal_amount',
                'labour_rate',
                'labour_amount',
                'other_amount',
                'total_amount',
            ]);
        });
    }
};
