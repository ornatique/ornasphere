<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            if (!Schema::hasColumn('sales', 'payment_mode')) {
                $table->string('payment_mode', 30)->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn('sales', 'payment_reference')) {
                $table->string('payment_reference', 120)->nullable()->after('payment_mode');
            }
            if (!Schema::hasColumn('sales', 'payment_note')) {
                $table->string('payment_note', 255)->nullable()->after('payment_reference');
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_returns', 'refund_paid_amount')) {
                $table->decimal('refund_paid_amount', 12, 2)->default(0)->after('return_total');
            }
            if (!Schema::hasColumn('sale_returns', 'refund_mode')) {
                $table->string('refund_mode', 30)->nullable()->after('refund_paid_amount');
            }
            if (!Schema::hasColumn('sale_returns', 'refund_reference')) {
                $table->string('refund_reference', 120)->nullable()->after('refund_mode');
            }
            if (!Schema::hasColumn('sale_returns', 'refund_note')) {
                $table->string('refund_note', 255)->nullable()->after('refund_reference');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            foreach (['payment_note', 'payment_reference', 'payment_mode'] as $col) {
                if (Schema::hasColumn('sales', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            foreach (['refund_note', 'refund_reference', 'refund_mode', 'refund_paid_amount'] as $col) {
                if (Schema::hasColumn('sale_returns', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};

