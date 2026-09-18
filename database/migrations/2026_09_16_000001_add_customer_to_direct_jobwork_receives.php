<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('jobwork_receives', function (Blueprint $table) {
            if (!Schema::hasColumn('jobwork_receives', 'customer_id')) {
                $table->foreignId('customer_id')
                    ->nullable()
                    ->after('job_worker_id')
                    ->constrained('customers')
                    ->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('jobwork_receives', function (Blueprint $table) {
            if (Schema::hasColumn('jobwork_receives', 'customer_id')) {
                $table->dropConstrainedForeignId('customer_id');
            }
        });
    }
};
