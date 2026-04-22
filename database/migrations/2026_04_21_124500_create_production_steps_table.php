<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->foreignId('labour_formula_id')->nullable()->constrained('labour_formulas')->nullOnDelete();
            $table->boolean('receivable_loss')->default(false);
            $table->boolean('auto_create_cost')->default(false);
            $table->foreignId('production_cost_id')->nullable()->constrained('production_costs')->nullOnDelete();
            $table->text('remarks')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();

            $table->unique(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_steps');
    }
};

