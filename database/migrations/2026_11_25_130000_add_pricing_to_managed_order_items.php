<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('managed_order_items', function (Blueprint $t) {
            foreach (['other_weight', 'purity', 'waste_percent', 'net_purity', 'total_fine_weight'] as $field) $t->decimal($field, 24, 3)->nullable();
            foreach (['metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount', 'sale_price', 'total_amount'] as $field) $t->decimal($field, 24, 2)->nullable();
            $t->string('remarks', 1000)->nullable();
        });
    }
    public function down(): void
    {
        Schema::table('managed_order_items', fn (Blueprint $t) => $t->dropColumn(['other_weight', 'purity', 'waste_percent', 'net_purity', 'total_fine_weight', 'metal_rate', 'metal_amount', 'labour_rate', 'labour_amount', 'other_amount', 'sale_price', 'total_amount', 'remarks']));
    }
};
