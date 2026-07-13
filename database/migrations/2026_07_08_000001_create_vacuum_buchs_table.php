<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('vacuum_buchs')) {
            return;
        }

        Schema::create('vacuum_buchs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('modified_count')->default(0);
            $table->string('buch_no');
            $table->decimal('size_inch', 10, 2)->nullable();
            $table->decimal('weight', 12, 3)->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'buch_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vacuum_buchs');
    }
};
