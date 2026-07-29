<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('jobwork_receive_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('jobwork_receive_id')->constrained('jobwork_receives')->cascadeOnDelete();
            $table->foreignId('jobwork_issue_item_id')->constrained('jobwork_issue_items')->cascadeOnDelete();
            $table->decimal('receive_gross_wt', 12, 3)->default(0);
            $table->decimal('receive_net_wt', 12, 3)->default(0);
            $table->decimal('receive_fine_wt', 12, 3)->default(0);
            $table->unsignedInteger('receive_qty_pcs')->default(0);
            $table->decimal('loss_wt', 12, 3)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->unique(['jobwork_receive_id', 'jobwork_issue_item_id'], 'jwr_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobwork_receive_items');
    }
};
