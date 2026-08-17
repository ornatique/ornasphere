<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('company_office_access_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->boolean('geo_enabled')->default(false);
            $table->boolean('device_approval_enabled')->default(true);
            $table->decimal('office_latitude', 10, 7)->nullable();
            $table->decimal('office_longitude', 10, 7)->nullable();
            $table->unsignedInteger('allowed_radius_meters')->default(100);
            $table->boolean('emergency_override_enabled')->default(false);
            $table->timestamp('emergency_override_until')->nullable();
            $table->text('emergency_override_reason')->nullable();
            $table->foreignId('emergency_override_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('company_id');
        });

        Schema::create('worker_allowed_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('device_id', 191);
            $table->string('device_name')->nullable();
            $table->string('platform')->nullable();
            $table->string('app_version')->nullable();
            $table->string('status')->default('pending');
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(['company_id', 'user_id', 'device_id'], 'worker_devices_company_user_device_unique');
            $table->index(['company_id', 'status']);
        });

        Schema::create('worker_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 191)->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->decimal('distance_meters', 10, 2)->nullable();
            $table->string('status');
            $table->string('reason')->nullable();
            $table->string('path')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['company_id', 'user_id', 'created_at']);
            $table->index(['company_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('worker_access_logs');
        Schema::dropIfExists('worker_allowed_devices');
        Schema::dropIfExists('company_office_access_settings');
    }
};
