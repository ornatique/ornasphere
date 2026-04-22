<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_step_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_step_id')->constrained('production_steps')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['production_step_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_step_user');
    }
};

