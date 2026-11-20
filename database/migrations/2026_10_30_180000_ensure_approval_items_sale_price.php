<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasColumn('approval_items', 'sale_price')) {
            Schema::table('approval_items', function (Blueprint $table) {
                $table->decimal('sale_price', 15, 2)->nullable();
            });
        }
    }

    public function down(): void
    {
        // This repair may find a pre-existing column containing voucher prices.
        // Keep it on rollback to preserve those snapshots.
    }
};
