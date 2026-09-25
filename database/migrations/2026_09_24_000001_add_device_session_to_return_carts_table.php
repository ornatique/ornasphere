<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('return_carts', function (Blueprint $table) {
            if (!Schema::hasColumn('return_carts', 'device_id')) {
                $table->string('device_id', 191)->nullable()->after('user_id');
            }

            if (!Schema::hasColumn('return_carts', 'voucher_session_id')) {
                $table->string('voucher_session_id', 191)->nullable()->after('device_id');
            }

            if (!Schema::hasColumn('return_carts', 'device_entry_id')) {
                $table->string('device_entry_id', 191)->nullable()->after('voucher_session_id');
            }
        });

        if (!$this->indexExists('return_carts', 'return_carts_device_session_idx')) {
            Schema::table('return_carts', function (Blueprint $table) {
                $table->index(['company_id', 'user_id', 'device_id', 'voucher_session_id'], 'return_carts_device_session_idx');
            });
        }

        if (!$this->indexExists('return_carts', 'return_carts_device_entry_idx')) {
            Schema::table('return_carts', function (Blueprint $table) {
                $table->index(['company_id', 'device_entry_id'], 'return_carts_device_entry_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('return_carts', 'return_carts_device_entry_idx')) {
            Schema::table('return_carts', function (Blueprint $table) {
                $table->dropIndex('return_carts_device_entry_idx');
            });
        }

        if ($this->indexExists('return_carts', 'return_carts_device_session_idx')) {
            Schema::table('return_carts', function (Blueprint $table) {
                $table->dropIndex('return_carts_device_session_idx');
            });
        }

        Schema::table('return_carts', function (Blueprint $table) {
            if (Schema::hasColumn('return_carts', 'device_entry_id')) {
                $table->dropColumn('device_entry_id');
            }

            if (Schema::hasColumn('return_carts', 'voucher_session_id')) {
                $table->dropColumn('voucher_session_id');
            }

            if (Schema::hasColumn('return_carts', 'device_id')) {
                $table->dropColumn('device_id');
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        return DB::table('information_schema.STATISTICS')
            ->where('TABLE_SCHEMA', DB::raw('DATABASE()'))
            ->where('TABLE_NAME', $table)
            ->where('INDEX_NAME', $index)
            ->exists();
    }
};
