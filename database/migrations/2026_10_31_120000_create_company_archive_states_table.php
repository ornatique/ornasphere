<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('company_archive_states', function (Blueprint $t) {
            $t->unsignedBigInteger('company_id')->primary();
            $t->string('status', 24)->default('active');
            $t->unsignedInteger('active_requests')->default(0);
            $t->uuid('archive_id')->nullable();
            $t->string('archive_path')->nullable();
            $t->string('archive_sha256', 64)->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->text('summary')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        if (\Illuminate\Support\Facades\DB::table('company_archive_states')->where('status', '!=', 'active')->exists()) {
            throw new RuntimeException('Restore or cancel every company archive before removing archive controls.');
        }
        Schema::dropIfExists('company_archive_states');
    }
};
