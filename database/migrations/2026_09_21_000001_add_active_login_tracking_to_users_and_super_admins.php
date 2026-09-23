<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('active_web_session_id', 191)->nullable()->after('remember_token');
            $table->unsignedBigInteger('active_api_token_id')->nullable()->after('active_web_session_id');
            $table->string('active_device_id', 191)->nullable()->after('active_api_token_id');
            $table->string('active_device_name')->nullable()->after('active_device_id');
            $table->string('active_platform', 100)->nullable()->after('active_device_name');
            $table->timestamp('active_login_at')->nullable()->after('active_platform');
        });

        Schema::table('super_admins', function (Blueprint $table) {
            $table->string('active_web_session_id', 191)->nullable()->after('password');
            $table->timestamp('active_login_at')->nullable()->after('active_web_session_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'active_web_session_id',
                'active_api_token_id',
                'active_device_id',
                'active_device_name',
                'active_platform',
                'active_login_at',
            ]);
        });

        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn([
                'active_web_session_id',
                'active_login_at',
            ]);
        });
    }
};
