<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('approval_headers') && Schema::hasTable('customers')) {
            Schema::table('approval_headers', function (Blueprint $table) {
                try {
                    $table->dropForeign(['customer_id']);
                } catch (\Throwable $e) {
                    // ignore if foreign key does not exist
                }
            });

            Schema::table('approval_headers', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('customers')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('approval_headers') && Schema::hasTable('users')) {
            Schema::table('approval_headers', function (Blueprint $table) {
                try {
                    $table->dropForeign(['customer_id']);
                } catch (\Throwable $e) {
                    // ignore
                }
            });

            Schema::table('approval_headers', function (Blueprint $table) {
                $table->foreign('customer_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            });
        }
    }
};

