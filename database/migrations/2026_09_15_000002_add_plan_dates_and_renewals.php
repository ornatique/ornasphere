<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'plan_started_at')) {
                $table->dateTime('plan_started_at')->nullable()->after('plan');
            }

            if (!Schema::hasColumn('companies', 'plan_expires_at')) {
                $table->dateTime('plan_expires_at')->nullable()->after('plan_started_at');
            }

            if (!Schema::hasColumn('companies', 'plan_renewed_at')) {
                $table->dateTime('plan_renewed_at')->nullable()->after('plan_expires_at');
            }
        });

        if (!Schema::hasTable('company_plan_renewals')) {
            Schema::create('company_plan_renewals', function (Blueprint $table) {
                $table->id();
                $table->foreignId('company_id')->constrained()->cascadeOnDelete();
                $table->dateTime('old_expires_at')->nullable();
                $table->dateTime('new_expires_at');
                $table->foreignId('renewed_by')->nullable()->constrained('super_admins')->nullOnDelete();
                $table->text('remarks')->nullable();
                $table->timestamps();

                $table->index(['company_id', 'created_at']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_plan_renewals');

        Schema::table('companies', function (Blueprint $table) {
            foreach (['plan_renewed_at', 'plan_expires_at', 'plan_started_at'] as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
