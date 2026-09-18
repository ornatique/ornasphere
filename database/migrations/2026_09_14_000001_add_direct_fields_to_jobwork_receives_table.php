<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receives', function (Blueprint $table) {
            if (!Schema::hasColumn('jobwork_receives', 'receive_no')) {
                $table->string('receive_no')->nullable()->after('jobwork_issue_id');
            }

            if (!Schema::hasColumn('jobwork_receives', 'job_worker_id')) {
                $table->foreignId('job_worker_id')->nullable()->after('receive_date')->constrained('job_workers')->nullOnDelete();
            }

            if (!Schema::hasColumn('jobwork_receives', 'production_step_id')) {
                $table->foreignId('production_step_id')->nullable()->after('job_worker_id')->constrained('production_steps')->nullOnDelete();
            }

            if (!Schema::hasColumn('jobwork_receives', 'receive_type')) {
                $table->string('receive_type', 20)->default('issue')->after('production_step_id');
            }
        });

        DB::statement('ALTER TABLE jobwork_receives MODIFY jobwork_issue_id BIGINT UNSIGNED NULL');

        Schema::table('jobwork_receives', function (Blueprint $table) {
            if (!$this->indexExists('jobwork_receives', 'jobwork_receives_company_receive_type_index')) {
                $table->index(['company_id', 'receive_type'], 'jobwork_receives_company_receive_type_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_receives', function (Blueprint $table) {
            if ($this->indexExists('jobwork_receives', 'jobwork_receives_company_receive_type_index')) {
                $table->dropIndex('jobwork_receives_company_receive_type_index');
            }

            foreach (['production_step_id', 'job_worker_id'] as $column) {
                if (Schema::hasColumn('jobwork_receives', $column)) {
                    $table->dropConstrainedForeignId($column);
                }
            }

            foreach (['receive_type', 'receive_no'] as $column) {
                if (Schema::hasColumn('jobwork_receives', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $database = $connection->getDatabaseName();

        return $connection->table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', $table)
            ->where('index_name', $index)
            ->exists();
    }
};
