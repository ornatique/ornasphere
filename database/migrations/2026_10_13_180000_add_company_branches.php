<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreignId('parent_company_id')->nullable()->constrained('companies')->restrictOnDelete();
            $table->string('branch_type', 20)->nullable();
        });
    }
    public function down(): void {
        if (\Illuminate\Support\Facades\DB::table('companies')->whereNotNull('parent_company_id')->exists()) {
            throw new \RuntimeException('Branches exist. Preserve their ownership before removing branch support.');
        }
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['parent_company_id']);
            $table->dropColumn(['parent_company_id', 'branch_type']);
        });
    }
};
