<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('worker_allowed_devices', function (Blueprint $table) {
            if (!Schema::hasColumn('worker_allowed_devices', 'last_latitude')) {
                $table->decimal('last_latitude', 10, 7)->nullable()->after('last_seen_at');
            }

            if (!Schema::hasColumn('worker_allowed_devices', 'last_longitude')) {
                $table->decimal('last_longitude', 10, 7)->nullable()->after('last_latitude');
            }
        });
    }

    public function down(): void
    {
        Schema::table('worker_allowed_devices', function (Blueprint $table) {
            if (Schema::hasColumn('worker_allowed_devices', 'last_longitude')) {
                $table->dropColumn('last_longitude');
            }

            if (Schema::hasColumn('worker_allowed_devices', 'last_latitude')) {
                $table->dropColumn('last_latitude');
            }
        });
    }
};
