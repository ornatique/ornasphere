<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            if (!Schema::hasColumn('item_sets', 'printed_at')) {
                $table->timestamp('printed_at')->nullable()->after('is_printed');
            }
        });
    }

    public function down(): void
    {
        Schema::table('item_sets', function (Blueprint $table) {
            if (Schema::hasColumn('item_sets', 'printed_at')) {
                $table->dropColumn('printed_at');
            }
        });
    }
};

