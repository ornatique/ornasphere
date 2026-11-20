<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('worker_access_logs') && !Schema::hasColumn('worker_access_logs', 'allowed_radius_meters')) {
            Schema::table('worker_access_logs', function (Blueprint $table) {
                $table->unsignedInteger('allowed_radius_meters')->nullable()->after('distance_meters');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('worker_access_logs') && Schema::hasColumn('worker_access_logs', 'allowed_radius_meters')) {
            Schema::table('worker_access_logs', function (Blueprint $table) {
                $table->dropColumn('allowed_radius_meters');
            });
        }
    }
};
