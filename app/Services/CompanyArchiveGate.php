<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** Database-backed request leases work across web instances; stale leases fail closed. */
class CompanyArchiveGate
{
    public const READABLE_STATES = ['preparing', 'ready', 'verified', 'archived', 'cleanup_pending'];
    public static function ready(): bool { return Schema::hasTable('company_archive_states'); }

    public static function readOnlyRequest(): bool
    {
        return app()->bound('request') && request()->attributes->get('company_archive_read_only') === true;
    }

    public static function state(int $id): ?object
    {
        return self::ready() ? DB::table('company_archive_states')->where('company_id', $id)->first() : null;
    }

    public static function ensure(int $id): void
    {
        DB::table('company_archive_states')->insertOrIgnore(['company_id' => $id, 'status' => 'active',
            'active_requests' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function enter(int $id): bool
    {
        if (!self::ready()) return true;
        return DB::transaction(function () use ($id) {
            self::ensure($id);
            $row = DB::table('company_archive_states')->where('company_id', $id)->lockForUpdate()->first();
            if ($row->status !== 'active') return false;
            DB::table('company_archive_states')->where('company_id', $id)->increment('active_requests');
            return true;
        });
    }

    public static function leave(int $id): void
    {
        if (self::ready()) DB::table('company_archive_states')->where('company_id', $id)->where('active_requests', '>', 0)->decrement('active_requests');
    }

    public static function enterRead(int $id): bool
    {
        if (!self::ready()) return false;
        return DB::transaction(function () use ($id) {
            $row=DB::table('company_archive_states')->where('company_id',$id)->lockForUpdate()->first();
            if (!$row || !in_array($row->status,self::READABLE_STATES,true)) return false;
            DB::table('company_archive_states')->where('company_id',$id)->increment('active_requests');
            return true;
        });
    }

    public static function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['archive' => $message]);
    }
}
