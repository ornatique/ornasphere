<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('category_people', function (Blueprint $table) {
            if (!Schema::hasColumn('category_people', 'is_system_default')) {
                $table->boolean('is_system_default')->default(false)->after('category_name');
            }
        });

        $now = now();
        foreach (DB::table('companies')->pluck('id') as $companyId) {
            foreach (['Customer', 'Worker'] as $defaultName) {
                $existing = DB::table('category_people')
                    ->where('company_id', $companyId)
                    ->whereRaw('LOWER(TRIM(category_name)) = ?', [strtolower($defaultName)])
                    ->first();

                if ($existing) {
                    DB::table('category_people')
                        ->where('id', $existing->id)
                        ->update([
                            'category_name' => $defaultName,
                            'is_system_default' => true,
                            'updated_at' => $now,
                        ]);

                    continue;
                }

                DB::table('category_people')->insert([
                    'company_id' => $companyId,
                    'category_name' => $defaultName,
                    'is_system_default' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('category_people', function (Blueprint $table) {
            if (Schema::hasColumn('category_people', 'is_system_default')) {
                $table->dropColumn('is_system_default');
            }
        });
    }
};
