<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->boolean('show_previous_bills')->default(false);
        });
    }
    public function down(): void
    {
        Schema::table('sales', fn (Blueprint $table) => $table->dropColumn('show_previous_bills'));
    }
};
