<?php

namespace App\Services;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Durable request identities with local OS-lock liveness; elapsed time is never proof. */
class CompanyArchiveRequestLeases
{
    public const TABLE = 'company_archive_request_leases';
    private Connection $connection;
    private string $directory;
    private ?bool $available = null;
    private array $owned = [];

    public function __construct()
    {
        $this->connection = DB::connection();
        $this->directory = storage_path('app/private/company-archive-request-leases');
    }

    public function available(): bool
    {
        // Unix threaded SAPIs may implement flock per process rather than per request.
        return $this->available ??= !(PHP_ZTS && PHP_OS_FAMILY !== 'Windows')
            && $this->connection->getSchemaBuilder()->hasTable(self::TABLE);
    }

    public function enter(int $companyId, array $states, bool $download = false): object|false
    {
        $lease = $this->newLock();
        try {
            $state = $this->connection->transaction(function () use ($companyId, $states, $download, $lease) {
                $this->connection->table('company_archive_states')->insertOrIgnore([
                    'company_id'=>$companyId,'status'=>'active','active_requests'=>0,'created_at'=>now(),'updated_at'=>now(),
                ]);
                $state = $this->connection->table('company_archive_states')->where('company_id',$companyId)->lockForUpdate()->first();
                if (!in_array($state->status,$states,true) || ($download && !$state->archive_path)) return false;
                $this->connection->table(self::TABLE)->insert([
                    'lease_id'=>$lease->id,'company_id'=>$companyId,'owner'=>$lease->owner,
                    'proof'=>$lease->proof,'file_identity'=>$lease->identity,'created_at'=>now(),
                ]);
                $this->connection->table('company_archive_states')->where('company_id',$companyId)->increment('active_requests');
                $state->active_requests++;
                return $state;
            });
            if (!$state) { $this->close($lease); return false; }
            $this->owned[$companyId][] = $lease;
            CompanyArchiveLog::lease('request_admitted',$companyId,[
                'lease_id'=>$lease->id,'lease_mode'=>$download ? 'download' : ($state->status==='active' ? 'active' : 'read_only'),
                'state'=>$state->status,'active_requests'=>(int)$state->active_requests,
            ]);
            return $state;
        } catch (\Throwable $e) { $this->close($lease); throw $e; }
    }

    public function leave(int $companyId, string $mode = 'release'): void
    {
        $key = array_key_last($this->owned[$companyId] ?? []);
        if ($key === null) return; // Never decrement a lease this request did not acquire.
        if ($this->connection->transactionLevel() !== 0) return;
        $lease = $this->owned[$companyId][$key];
        $this->connection->transaction(function () use ($companyId, $lease) {
            $this->connection->table('company_archive_states')->where('company_id',$companyId)->lockForUpdate()->first();
            $deleted = $this->connection->table(self::TABLE)->where('lease_id',$lease->id)->where('company_id',$companyId)->delete();
            if ($deleted) $this->connection->table('company_archive_states')->where('company_id',$companyId)->where('active_requests','>',0)->decrement('active_requests');
        });
        unset($this->owned[$companyId][$key]);
        $this->close($lease);
        try {
            $state = $this->connection->table('company_archive_states')->where('company_id',$companyId)->first();
            CompanyArchiveLog::lease($mode==='shutdown' ? 'request_recovered' : 'request_released',$companyId,[
                'lease_id'=>$lease->id,'lease_mode'=>$mode,'state'=>$state?->status,'active_requests'=>$state ? (int)$state->active_requests : null,
            ]);
        } catch (\Throwable) {} // The counter has already been released successfully.
    }

    /** Caller holds the company state row lock, serializing admission/release/reaping. */
    public function reconcile(object $state): void
    {
        if (!$this->available()) return;
        CompanyArchiveGate::check($this->connection->transactionLevel()>0,'Request cleanup requires a locked archive transition.');
        $rows = $this->connection->table(self::TABLE)->where('company_id',$state->company_id)->get();
        CompanyArchiveGate::check((int)$state->active_requests >= $rows->count(),'Request tracking does not match its counter. Review archive controls before retrying.');
        $owner = $this->owner(false);
        foreach ($rows as $row) {
            if (!$owner || !hash_equals($owner,$row->owner) || !Str::isUuid($row->lease_id)) continue;
            $path = $this->directory.'/'.$row->lease_id.'.lock';
            // A missing/replaced file or a different server is not evidence of completion.
            if (is_link($path) || !is_file($path)) continue;
            $handle = @fopen($path,'r+b');
            if (!$handle) continue;
            try {
                if (!flock($handle,LOCK_EX|LOCK_NB)) continue; // Still owned: even a very old request stays counted.
                clearstatcache(true,$path);
                $stat = fstat($handle); $pathStat = @stat($path);
                if (!$stat || !$pathStat || empty($stat['ino']) || $row->file_identity !== $this->identity($stat)
                    || $this->identity($stat) !== $this->identity($pathStat)) continue;
                rewind($handle); $proof = stream_get_contents($handle,128);
                if (!is_string($proof) || !hash_equals($row->proof,hash('sha256',$proof))) continue;
                $removed = $this->connection->table(self::TABLE)->where('lease_id',$row->lease_id)->where('company_id',$state->company_id)->delete();
                if ($removed) {
                    $this->connection->table('company_archive_states')->where('company_id',$state->company_id)->where('active_requests','>',0)->decrement('active_requests');
                    $state->active_requests--;
                    $metadata = [
                        'lease_id'=>$row->lease_id,'active_requests'=>(int)$state->active_requests,'state'=>$state->status,
                    ];
                    $companyId=(int)$state->company_id;
                    $this->connection->afterCommit(static fn()=>CompanyArchiveLog::lease('stale_request_cleared',$companyId,$metadata));
                }
                // Keep this rare stale lock file: the outer group transaction may still roll back.
            } finally { fclose($handle); }
        }
        if ((int)$state->active_requests > $this->connection->table(self::TABLE)->where('company_id',$state->company_id)->count()) {
            CompanyArchiveLog::lease('untracked_requests_retained',(int)$state->company_id,[
                'active_requests'=>(int)$state->active_requests,'state'=>$state->status,
            ]);
        }
    }

    private function owner(bool $create): ?string
    {
        if (!is_dir($this->directory)) {
            if (!$create) return null;
            if (!@mkdir($this->directory,0700,true) && !is_dir($this->directory)) throw new \RuntimeException('Private request lock storage is unavailable.');
        }
        $path = $this->directory.'/node-id';
        if (is_link($this->directory) || is_link($path) || (!$create && !is_file($path))) return null;
        $handle = @fopen($path,$create ? 'c+b' : 'rb');
        if (!$handle) return null;
        try {
            if (!flock($handle,$create ? LOCK_EX : LOCK_SH)) return null;
            $node = stream_get_contents($handle,128);
            if ($node==='' && $create) {
                $node = bin2hex(random_bytes(32));
                if (fwrite($handle,$node)!==strlen($node) || !fflush($handle)) return null;
                @chmod($path,0600);
            }
            if (!is_string($node) || !preg_match('/^[a-f0-9]{64}$/D',$node)) return null;
            return hash('sha256',gethostname().'|'.realpath($this->directory).'|'.$node);
        } finally { fclose($handle); }
    }

    private function newLock(): object
    {
        $owner = $this->owner(true);
        if (!$owner) throw new \RuntimeException('Private request lock identity is unavailable.');
        $id = (string)Str::uuid(); $path = $this->directory.'/'.$id.'.lock';
        $handle = @fopen($path,'x+b');
        if (!$handle) throw new \RuntimeException('Cannot create private request lock.');
        try {
            if (!flock($handle,LOCK_EX|LOCK_NB)) throw new \RuntimeException('Cannot hold private request lock.');
            $token = bin2hex(random_bytes(32));
            if (fwrite($handle,$token)!==strlen($token) || !fflush($handle)) throw new \RuntimeException('Cannot save private request lock proof.');
            @chmod($path,0600); $stat = fstat($handle);
            if (!$stat || empty($stat['ino'])) throw new \RuntimeException('Request lock file identity is not supported on this filesystem.');
            return (object)['id'=>$id,'owner'=>$owner,'proof'=>hash('sha256',$token),'identity'=>$this->identity($stat),'handle'=>$handle,'path'=>$path];
        } catch (\Throwable $e) { fclose($handle); @unlink($path); throw $e; }
    }

    private function identity(array $stat): string { return $stat['dev'].':'.$stat['ino']; }

    private function close(object $lease): void
    {
        if (is_resource($lease->handle)) fclose($lease->handle);
        @unlink($lease->path);
    }
}
