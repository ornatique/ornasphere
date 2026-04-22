<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_workers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('mobile_no', 20)->nullable();
            $table->text('address')->nullable();
            $table->string('city', 191)->nullable();
            $table->string('area', 191)->nullable();
            $table->string('landmark', 191)->nullable();
            $table->string('pincode', 20)->nullable();
            $table->string('contact_person1_name', 191)->nullable();
            $table->string('contact_person1_phone', 20)->nullable();
            $table->string('contact_person2_name', 191)->nullable();
            $table->string('contact_person2_phone', 20)->nullable();
            $table->string('gst_no', 191)->nullable();
            $table->string('pan_no', 191)->nullable();
            $table->string('aadhaar_no', 191)->nullable();
            $table->date('birth_date')->nullable();
            $table->date('anniversary_date')->nullable();
            $table->string('reference', 191)->nullable();
            $table->text('remarks')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['company_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_workers');
    }
};

