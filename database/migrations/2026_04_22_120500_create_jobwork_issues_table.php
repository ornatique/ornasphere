<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobwork_issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('voucher_no', 50)->nullable();
            $table->date('jobwork_date');
            $table->foreignId('job_worker_id')->constrained('job_workers')->cascadeOnDelete();
            $table->foreignId('production_step_id')->constrained('production_steps')->cascadeOnDelete();
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('modified_count')->default(0);
            $table->timestamps();

            $table->index(['company_id', 'jobwork_date']);
            $table->index(['company_id', 'voucher_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobwork_issues');
    }
};

