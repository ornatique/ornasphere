<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::table('category_people')->whereRaw('LOWER(TRIM(category_name)) = ?', ['supplier'])
            ->update(['category_name'=>'Bullion','is_system_default'=>true,'updated_at'=>now()]);
        Schema::table('companies', function (Blueprint $table) {
            $table->date('opening_balance_date')->nullable();
            $table->decimal('opening_cash', 18, 2)->default(0);
            $table->decimal('opening_bank', 18, 2)->default(0);
            $table->decimal('opening_gold_fine', 18, 3)->default(0);
            $table->decimal('opening_silver_fine', 18, 3)->default(0);
        });
    }
    public function down(): void
    {
        Schema::table('companies', fn (Blueprint $table) => $table->dropColumn(['opening_balance_date','opening_cash','opening_bank','opening_gold_fine','opening_silver_fine']));
        // Preserve the Bullion category and person links.
    }
};
