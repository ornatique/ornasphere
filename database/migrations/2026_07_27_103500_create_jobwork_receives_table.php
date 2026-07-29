<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobwork_receives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('jobwork_issue_id')->constrained('jobwork_issues')->cascadeOnDelete();
            $table->date('receive_date');
            $table->text('remarks')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('modified_count')->default(0);
            $table->timestamps();

            $table->unique(['company_id', 'jobwork_issue_id']);
            $table->index(['company_id', 'receive_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobwork_receives');
    }
};
