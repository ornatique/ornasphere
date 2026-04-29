<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_headers', 'remarks')) {
                $table->text('remarks')->nullable()->after('status');
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            if (!Schema::hasColumn('sale_returns', 'remarks')) {
                $table->text('remarks')->nullable()->after('return_total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_headers', function (Blueprint $table) {
            if (Schema::hasColumn('approval_headers', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });

        Schema::table('sale_returns', function (Blueprint $table) {
            if (Schema::hasColumn('sale_returns', 'remarks')) {
                $table->dropColumn('remarks');
            }
        });
    }
};

