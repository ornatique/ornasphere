<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('managed_orders', fn (Blueprint $t) => $t->string('price_mode', 16)->nullable());
    }
    public function down(): void
    {
        Schema::table('managed_orders', fn (Blueprint $t) => $t->dropColumn('price_mode'));
    }
};
