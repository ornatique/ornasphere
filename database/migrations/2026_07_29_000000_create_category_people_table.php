<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('category_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('category_name');
            $table->timestamps();

            $table->unique(['company_id', 'category_name']);
        });

        $now = now();
        foreach (['view', 'create', 'edit', 'delete', 'manage'] as $action) {
            DB::table('permissions')->updateOrInsert(
                [
                    'name' => "category-person-{$action}",
                    'guard_name' => 'web',
                ],
                [
                    'company_id' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }
    }

    public function down(): void
    {
        DB::table('permissions')
            ->whereIn('name', [
                'category-person-view',
                'category-person-create',
                'category-person-edit',
                'category-person-delete',
                'category-person-manage',
            ])
            ->where('guard_name', 'web')
            ->delete();

        Schema::dropIfExists('category_people');
    }
};
