<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_headers', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_headers', 'employee_id')) {
                $table->foreignId('employee_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('approval_headers', 'modified_count')) {
                $table->unsignedInteger('modified_count')->default(0)->after('employee_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('approval_headers', function (Blueprint $table) {
            if (Schema::hasColumn('approval_headers', 'employee_id')) {
                $table->dropConstrainedForeignId('employee_id');
            }

            if (Schema::hasColumn('approval_headers', 'modified_count')) {
                $table->dropColumn('modified_count');
            }
        });
    }
};

