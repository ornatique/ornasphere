<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('approval_items', function (Blueprint $table) {
            if (!Schema::hasColumn('approval_items', 'device_id')) {
                $table->string('device_id', 191)->nullable()->after('approval_id');
            }

            if (!Schema::hasColumn('approval_items', 'voucher_session_id')) {
                $table->string('voucher_session_id', 191)->nullable()->after('device_id');
            }
        });

        if (!$this->indexExists('approval_items', 'approval_items_device_session_idx')) {
            Schema::table('approval_items', function (Blueprint $table) {
                $table->index(['approval_id', 'device_id', 'voucher_session_id'], 'approval_items_device_session_idx');
            });
        }
    }

    public function down(): void
    {
        if ($this->indexExists('approval_items', 'approval_items_device_session_idx')) {
            Schema::table('approval_items', function (Blueprint $table) {
                $table->dropIndex('approval_items_device_session_idx');
            });
        }

        Schema::table('approval_items', function (Blueprint $table) {
            if (Schema::hasColumn('approval_items', 'voucher_session_id')) {
                $table->dropColumn('voucher_session_id');
            }

            if (Schema::hasColumn('approval_items', 'device_id')) {
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
