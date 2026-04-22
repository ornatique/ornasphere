<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobwork_issue_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobwork_issue_id')->constrained('jobwork_issues')->cascadeOnDelete();
            $table->foreignId('item_id')->constrained('items')->cascadeOnDelete();
            $table->decimal('gross_wt', 12, 3)->default(0);
            $table->decimal('other_wt', 12, 3)->default(0);
            $table->decimal('net_wt', 12, 3)->default(0);
            $table->decimal('fine_wt', 12, 3)->default(0);
            $table->unsignedInteger('qty_pcs')->default(0);
            $table->text('remarks')->nullable();
            $table->decimal('total_amt', 12, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobwork_issue_items');
    }
};

