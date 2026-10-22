<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('companies')->orderBy('id')->chunkById(200, function ($companies) {
            foreach ($companies as $company) {
                $existing = DB::table('category_people')->where('company_id', $company->id)
                    ->whereRaw('LOWER(TRIM(category_name)) = ?', ['supplier'])->first();
                if ($existing) {
                    DB::table('category_people')->where('id', $existing->id)->update([
                        'category_name'=>'Supplier', 'is_system_default'=>true, 'updated_at'=>now(),
                    ]);
                } else {
                    DB::table('category_people')->insert([
                        'company_id'=>$company->id, 'category_name'=>'Supplier',
                        'is_system_default'=>true, 'created_at'=>now(), 'updated_at'=>now(),
                    ]);
                }
            }
        });
    }

    public function down(): void
    {
        // Preserve categories and their linked persons on rollback.
    }
};
