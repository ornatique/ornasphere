<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('job_workers')) {
            return;
        }

        if (!Schema::hasColumn('job_workers', 'person_id')) {
            Schema::table('job_workers', function (Blueprint $table) {
                $table->unsignedBigInteger('person_id')->nullable()->after('company_id');
            });
        }

        try {
            Schema::table('job_workers', function (Blueprint $table) {
                $table->index('person_id', 'job_workers_person_id_index');
            });
        } catch (Throwable $e) {
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('job_workers') || !Schema::hasColumn('job_workers', 'person_id')) {
            return;
        }

        try {
            Schema::table('job_workers', function (Blueprint $table) {
                $table->dropIndex('job_workers_person_id_index');
            });
        } catch (Throwable $e) {
        }

        Schema::table('job_workers', function (Blueprint $table) {
            $table->dropColumn('person_id');
        });
    }
};
