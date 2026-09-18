<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('companies', 'plan')) {
            DB::statement("ALTER TABLE companies MODIFY plan VARCHAR(255) NULL");
            DB::table('companies')->where('plan', 'free')->update(['plan' => null]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('companies', 'plan')) {
            DB::table('companies')->whereNull('plan')->update(['plan' => 'free']);
            DB::statement("ALTER TABLE companies MODIFY plan VARCHAR(255) NOT NULL DEFAULT 'free'");
        }
    }
};
