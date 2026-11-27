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
        // INSERT IGNORE on an existing InnoDB row can take a shared duplicate-key lock.
        // Concurrent requests then deadlock when both try to upgrade it to FOR UPDATE.
        if (DB::table('company_archive_states')->where('company_id', $id)->exists()) return;
        DB::table('company_archive_states')->insertOrIgnore(['company_id' => $id, 'status' => 'active',
            'active_requests' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public static function enter(int $id): bool
    {
        if (!self::ready()) return true;
        if (self::tracker()->available()) return self::tracker()->enter($id,['active']) !== false;
        return DB::transaction(function () use ($id) {
            self::ensure($id);
            $row = DB::table('company_archive_states')->where('company_id', $id)->lockForUpdate()->first();
            if ($row->status !== 'active') {
                self::trace('request_denied', $id, 'active', $row);
                return false;
            }
            DB::table('company_archive_states')->where('company_id', $id)->increment('active_requests');
            $row->active_requests++;
            self::trace('request_admitted', $id, 'active', $row);
            return true;
        }, 5);
    }

    public static function leave(int $id): void
    {
        if (self::tracker()->available()) { self::tracker()->leave($id); return; }
        if (self::ready()) {
            DB::transaction(function () use ($id) {
                DB::table('company_archive_states')->where('company_id', $id)->where('active_requests', '>', 0)->decrement('active_requests');
            }, 5);
            // The release is already complete; diagnostic reads may not fail the request.
            try { self::trace('request_released', $id, 'release', self::state($id)); } catch (\Throwable) {}
        }
    }

    public static function enterRead(int $id): bool
    {
        if (!self::ready()) return false;
        if (self::tracker()->available()) return self::tracker()->enter($id,self::READABLE_STATES) !== false;
        return DB::transaction(function () use ($id) {
            $row=DB::table('company_archive_states')->where('company_id',$id)->lockForUpdate()->first();
            if (!$row || !in_array($row->status,self::READABLE_STATES,true)) return false;
            DB::table('company_archive_states')->where('company_id',$id)->increment('active_requests');
            $row->active_requests++;
            self::trace('request_admitted', $id, 'read_only', $row);
            return true;
        }, 5);
    }

    public static function enterDownload(int $id): object
    {
        if (self::tracker()->available()) {
            $state = self::tracker()->enter($id,['ready','verified'],true);
            self::check($state !== false,'No downloadable archive is ready.');
            return $state;
        }
        return DB::transaction(function () use ($id) {
            $state = DB::table('company_archive_states')->where('company_id',$id)->lockForUpdate()->first();
            self::check($state && in_array($state->status,['ready','verified'],true) && $state->archive_path,'No downloadable archive is ready.');
            DB::table('company_archive_states')->where('company_id',$id)->increment('active_requests');
            return $state;
        }, 5);
    }

    public static function reconcile(object $state): void { self::tracker()->reconcile($state); }

    private static function tracker(): CompanyArchiveRequestLeases
    {
        if (!app()->bound(CompanyArchiveRequestLeases::class)) app()->instance(CompanyArchiveRequestLeases::class,new CompanyArchiveRequestLeases);
        return app(CompanyArchiveRequestLeases::class);
    }

    /** Callers add only counters they acquired; never reconciles somebody else's count. */
    public static function protectRequests(array &$leases): \Closure
    {
        $connection = DB::connection();
        $initialLevel = $connection->transactionLevel();
        $tracker = self::tracker(); $tracked = $tracker->available();
        $reserve = str_repeat('x', 64 * 1024);
        register_shutdown_function(static function () use (&$leases, &$reserve, $connection, $initialLevel, $tracker, $tracked) {
            $reserve = null;
            if (!$leases) return;
            try {
                // An abandoned transaction would roll back when PDO closes anyway. Do that
                // first so releasing our request counter is not rolled back along with it.
                while ($connection->transactionLevel() > $initialLevel) $connection->rollBack();
                if ($connection->transactionLevel() !== 0) {
                    foreach ($leases as $id) self::trace('request_cleanup_deferred', $id, 'shutdown', null);
                    return;
                }
                foreach ($leases as $key => $id) {
                    unset($leases[$key]);
                    try {
                        if ($tracked) { $tracker->leave($id,'shutdown'); continue; }
                        $connection->table('company_archive_states')->where('company_id',$id)->where('active_requests','>',0)->decrement('active_requests');
                        self::trace('request_recovered', $id, 'shutdown', $connection->table('company_archive_states')->where('company_id',$id)->first());
                    } catch (\Throwable) { self::trace('request_cleanup_failed', $id, 'shutdown', null); }
                }
            } catch (\Throwable) {
                foreach ($leases as $id) self::trace('request_cleanup_failed', $id, 'shutdown', null);
            }
        });
        return static function () use (&$leases, $connection, $initialLevel) {
            // Let shutdown release after any unfinished controller transaction is discarded.
            if ($connection->transactionLevel() > $initialLevel) return;
            foreach ($leases as $key => $id) {
                unset($leases[$key]);
                try { self::leave($id); }
                catch (\Throwable $e) { $leases[$key] = $id; throw $e; }
            }
        };
    }

    public static function check(bool $condition, string $message): void
    {
        if (!$condition) throw ValidationException::withMessages(['archive' => $message]);
    }

    private static function trace(string $event, int $id, string $mode, ?object $state): void
    {
        CompanyArchiveLog::lease($event, $id, [
            'lease_mode'=>$mode, 'state'=>$state?->status,
            'active_requests'=>$state ? (int)$state->active_requests : null,
            'state_updated_at'=>$state?->updated_at,
        ]);
    }
}
