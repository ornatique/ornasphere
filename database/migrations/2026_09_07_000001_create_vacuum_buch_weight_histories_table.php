<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vacuum_buch_weight_histories')) {
            return;
        }

        Schema::create('vacuum_buch_weight_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vacuum_buch_id')->constrained('vacuum_buchs')->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_weight', 12, 3)->nullable();
            $table->decimal('new_weight', 12, 3)->nullable();
            $table->timestamp('changed_at');
            $table->timestamps();

            $table->index(['company_id', 'vacuum_buch_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacuum_buch_weight_histories');
    }
};
