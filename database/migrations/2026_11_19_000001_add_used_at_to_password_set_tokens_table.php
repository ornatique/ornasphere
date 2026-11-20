<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('password_set_tokens', 'used_at')) {
            Schema::table('password_set_tokens', function (Blueprint $table) {
                $table->timestamp('used_at')->nullable()->after('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('password_set_tokens', 'used_at')) {
            Schema::table('password_set_tokens', function (Blueprint $table) {
                $table->dropColumn('used_at');
            });
        }
    }
};
