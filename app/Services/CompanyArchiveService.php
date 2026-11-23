<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CompanyArchiveService
{
    public function __construct(private CompanyArchiveInventory $inventory, private CompanyArchivePackage $package) {}

    private function update(int $id, array $values): void
    {
        CompanyArchiveScope::update($id, $values);
    }

    private function transition(int $id, array $from, string $to): object
    {
        return CompanyArchiveScope::transition($id, $from, $to);
    }

    private function queueIdle(int|array $companyId): void
    {
        foreach ([config('queue.default'), config('stock_gallery.queue_connection', 'database')] as $connection) {
            CompanyArchiveGate::check(in_array($connection, ['database', 'sync'], true), 'Archive requires drained database/sync queues. External queues need a reviewed worker drain before this feature can be enabled.');
        }
        foreach ((array)$companyId as $id) CompanyArchiveGate::check(!app(CompanyArchiveQueue::class)->blocksCompany($id), 'Queued/running jobs for this company/branch or unclassified jobs must finish before archiving. Other companies are not paused.');
    }

    public function prepare(Company $company, int $actor, string $password): array
    {
        $this->inventory->refreshMetadata();
        return CompanyArchiveLog::run('prepare',(int)$company->id,fn()=>$this->prepareArchive($company,$actor,$password));
    }

    private function prepareArchive(Company $company, int $actor, string $password): array
    {
        CompanyArchiveLog::step('checking_runtime');
        CompanyArchiveGate::check((bool) config('company_archive.enabled'), 'Archive is not enabled on this deployment. Complete the deployment checks first.');
        CompanyArchiveGate::check(CompanyArchiveGate::ready(), 'Run the company archive migration before using this feature.');
        $this->package->assertAvailable();
        CompanyArchiveLog::step('checking_scope');
        CompanyArchiveScope::stateMembers((int)$company->id);
        CompanyArchiveScope::resumeFailedPreparation((int)$company->id);
        $members=CompanyArchiveScope::family($company); $ids=CompanyArchiveScope::ids($members);
        CompanyArchiveLog::step('checking_schema_policy');
        $this->inventory->coverage(); $this->inventory->assertTransactional($ids);
        CompanyArchiveLog::step('checking_dependency_preflight');
        $preflightOrder=$this->inventory->order();
        $this->inventory->assertNoForeignDependants($ids,$preflightOrder);
        CompanyArchiveLog::step('checking_queue'); $this->queueIdle($ids);
        CompanyArchiveLog::step('checking_staff');
        $staff = app(CompanyArchiveUsers::class);
        $staffIds = $staff->ids($ids);
        $staff->review($ids, $staffIds); $this->assertStaffTransactional();
        CompanyArchiveGate::check(!in_array($actor, $staffIds, true), 'The company administrator account must stay available for restore.');
        CompanyArchiveLog::step('locating_images');
        $references = $this->inventory->fileReferences($ids, $staffIds);
        // Check local files first: incomplete development copies must not pause the workspace.
        uasort($references, fn ($a, $b) => $this->isLocalFile($b) <=> $this->isLocalFile($a));
        $preflightIndex = 0;
        foreach ($references as $file) {
            // Cloud files stay in their original storage; the archive keeps only the signed reference.
            if (!$this->isLocalFile($file)) continue;
            CompanyArchiveLog::step('checking_image_storage',['file_index'=>++$preflightIndex,'total_files'=>count($references)]);
            try { $exists = CompanyArchiveInventory::disk($file['disk'])->exists($file['path']); }
            catch (\Throwable $e) { CompanyArchiveGate::check(false, 'Company image storage is unavailable. Check storage access before archiving; company entries have not been paused.'); }
            if (!$exists && $this->inventory->isOptionalImageAttachment($file)) {
                CompanyArchiveLog::step('optional_image_missing',['file_index'=>$preflightIndex]);
                continue;
            }
            CompanyArchiveGate::check($exists, 'An attachment is missing from this company copy ('.$this->inventory->attachmentSourceHint($file).'). This file is configured on local storage; cloud path-only backup does not include it. Restore or re-upload the local attachment before archiving; company entries have not been paused.');
        }
        // Verify private temporary storage before pausing any workspace.
        CompanyArchiveLog::step('creating_temporary_workspace');
        $work = $this->package->workspace(); $building = false;
        $attempt = CompanyArchiveLog::reference() ?? (string) Str::uuid(); $stage = 'checking_records'; $detail = null;
        $armed = false; $reserve = null;
        try {
            // Freeze first, commit it, then wait for previously admitted requests to drain.
            CompanyArchiveLog::step('pausing_workspaces'); CompanyArchiveScope::freeze($company,$members);
            CompanyArchiveLog::step('waiting_for_requests');
            $this->transition($company->id, ['preparing'], 'building'); $building = true;
            $armed = true; $reserve = str_repeat('x', 256 * 1024); $companyId = (int)$company->id;
            // Keep the last safe stage in the DB even when file logging is unavailable.
            register_shutdown_function(static function () use (&$armed, &$reserve, &$stage, &$detail, $companyId, $attempt) {
                if (!$armed) return;
                $reserve = null; $error = error_get_last();
                if (!$error || !in_array($error['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR,E_USER_ERROR], true)) return;
                $message = $error['message'] ?? '';
                $code = str_contains($message,'Allowed memory size') ? 'MEMORY_LIMIT' : (str_contains($message,'Maximum execution time') ? 'TIME_LIMIT' : 'PHP_FATAL');
                try { self::recordBuildProgress($companyId,$attempt,$stage,$detail,$code); } catch (\Throwable) {}
                // Never reopen a possibly interrupted operation automatically.
            });
            self::recordBuildProgress($companyId,$attempt,$stage);
            $id = (string) Str::uuid();
            CompanyArchiveLog::archive($id);
            $this->queueIdle($ids);
            $order = $preflightOrder;
            $this->inventory->assertNoForeignDependants($ids, $order);
            $staffIds = $staff->ids($ids);
            $staff->review($ids, $staffIds);
            CompanyArchiveGate::check(!in_array($actor, $staffIds, true), 'The company administrator account must stay available for restore.');
            $staffTables = array_values(array_intersect(CompanyArchiveUsers::TABLES, $this->inventory->tables()));
            $stage = 'reading_schema'; self::recordBuildProgress($companyId,$attempt,$stage);
            $manifest = ['version' => 5, 'media_policy'=>'cloud_paths_only', 'reference_files'=>[], 'missing_optional_files'=>[],
                'staff'=>['ids'=>$staffIds, 'access'=>$staff->access($staffIds), 'links'=>$staff->links($ids, $staffIds, $order)],
                'archive_id' => $id, 'company_id' => (int) $company->id, 'company_slug' => $company->slug,
                'created_at' => now()->toIso8601String(), 'schema' => $this->inventory->schema(),
                'policy' => hash('sha256', json_encode(config('company_archive'))), 'order' => $order,
                'tables' => [], 'files' => [], 'entries' => [], 'shared_legacy' => $this->inventory->sharedLegacy()];
            if (count($ids)>1) $manifest['companies']=$members;
            $sources = []; $index = 0; $total = 0;
            $retained = array_values(array_diff(array_intersect(config('company_archive.retained'), $this->inventory->tables()), $staffTables));
            foreach (array_merge($order, $staffTables, $retained) as $table) {
                $stage = 'exporting_records'; $detail = $table; self::recordBuildProgress($companyId,$attempt,$stage,$detail);
                $entry = 'data/'.(++$index).'.jsonl'; $local = $work.'/'.($index).'.jsonl';
                $stream = fopen($local, 'wb'); $count = 0;
                try {
                    $query = in_array($table, $staffTables, true) ? $staff->ordered($table, $staffIds) : $this->inventory->ordered($table, $ids);
                    foreach ($query->cursor() as $row) {
                        if (in_array($table, $staffTables, true)) $row = $staff->row($table, $row);
                        $line = json_encode(CompanyArchiveInventory::encodeRow($row), JSON_THROW_ON_ERROR)."\n";
                        CompanyArchiveGate::check(fwrite($stream, $line) === strlen($line), 'Cannot write temporary archive data.'); $count++;
                        if ($count % 1000 === 0) CompanyArchiveLog::step('exporting_records_progress',['table'=>$table,'rows'=>$count]);
                    }
                } finally { fclose($stream); @chmod($local, 0600); }
                $meta = ['size' => filesize($local), 'sha256' => hash_file('sha256', $local)];
                $manifest['tables'][$table] = ['entry' => $entry, 'count' => $count, 'mode' => in_array($table, $staffTables, true) ? 'staff_move' : (in_array($table, $order, true) ? 'move' : 'retained'), ...$meta];
                $manifest['entries'][$entry] = $meta; $sources[$entry] = $local; $total += $meta['size'];
                CompanyArchiveLog::step('table_exported',['table'=>$table,'rows'=>$count,'bytes'=>$meta['size']]);
                CompanyArchiveGate::check($total <= config('company_archive.max_bytes'), 'Company exceeds the configured archive size limit.');
            }
            $stage = 'checking_images'; $detail = null; self::recordBuildProgress($companyId,$attempt,$stage);
            $owned = $this->inventory->fileReferences($ids, $staffIds);
            $all = $this->inventory->fileReferences(null);
            foreach ($owned as $key => $file) {
                if (!$this->isLocalFile($file)) {
                    $manifest['reference_files'][$key] = $file;
                    CompanyArchiveLog::step('cloud_reference_saved',['file_index'=>count($manifest['reference_files'])]);
                    continue;
                }
                $stage = 'copying_images'; $detail = (string)(count($manifest['files'])+1); self::recordBuildProgress($companyId,$attempt,$stage,$detail);
                $disk = CompanyArchiveInventory::disk($file['disk']);
                $exists = $disk->exists($file['path']);
                if (!$exists && $this->inventory->isOptionalImageAttachment($file)) {
                    $manifest['missing_optional_files'][$key] = ['reference'=>$file,'source'=>$this->inventory->attachmentSourceHint($file)];
                    CompanyArchiveLog::step('optional_image_skipped',['file_index'=>count($manifest['missing_optional_files'])]);
                    continue;
                }
                CompanyArchiveGate::check($exists, 'An attachment is missing. Repair missing files before archiving.');
                $entry = 'files/'.(++$index).'.bin'; $local = $work.'/'.$index.'.bin';
                $input = $disk->readStream($file['path']); $output = fopen($local, 'wb');
                CompanyArchiveGate::check(is_resource($input) && is_resource($output), 'Cannot read an attachment.');
                try { $copied = stream_copy_to_stream($input, $output, config('company_archive.max_bytes') - $total + 1); }
                finally { fclose($input); fclose($output); @chmod($local, 0600); }
                $total += $copied;
                CompanyArchiveGate::check($total <= config('company_archive.max_bytes') && $copied === $disk->size($file['path']), 'Attachment exceeds archive limit or changed during export.');
                $meta = ['size' => filesize($local), 'sha256' => hash_file('sha256', $local)];
                $manifest['files'][$key] = [...$file, 'shared' => $file['retained'] || ($all[$key]['references'] ?? 0) > $file['references'], 'entry' => $entry, ...$meta];
                $manifest['entries'][$entry] = $meta; $sources[$entry] = $local;
                CompanyArchiveLog::step('image_copied',['file_index'=>count($manifest['files']),'bytes'=>$meta['size']]);
            }
            $zipPath = $work.'/archive.zip';
            $stage = 'encrypting_zip'; $detail = null; self::recordBuildProgress($companyId,$attempt,$stage);
            $this->package->create($zipPath, $password, $manifest, $sources);
            $stage = 'verifying_zip'; self::recordBuildProgress($companyId,$attempt,$stage);
            $this->package->inspect($zipPath, $password);
            $stage = 'saving_zip'; self::recordBuildProgress($companyId,$attempt,$stage);
            $vaultPath = 'company-archives/'.$company->id.'/'.$id.'.zip';
            $stream = fopen($zipPath, 'rb');
            try { $saved = Storage::disk(config('company_archive.disk'))->put($vaultPath, $stream, ['visibility' => 'private']); }
            finally { fclose($stream); }
            CompanyArchiveGate::check($saved, 'Encrypted archive could not be saved.');
            $summary = ['records' => array_map(fn ($t) => ['count' => $t['count'], 'mode' => $t['mode']], $manifest['tables']),
                'files' => count($manifest['files']), 'shared_files_retained' => count(array_filter($manifest['files'], fn ($f) => $f['shared'])),
                'shared_legacy' => $manifest['shared_legacy'], 'bytes' => filesize($zipPath), 'staff_accounts'=>true, 'staff_count'=>count($staffIds),
                'media_policy'=>'cloud_paths_only', 'cloud_references'=>count($manifest['reference_files']),
                'missing_optional_images'=>count($manifest['missing_optional_files']),
                'missing_optional_sources'=>array_column($manifest['missing_optional_files'],'source')];
            if (count($ids)>1) $summary += ['companies'=>$members,'group_root_id'=>(int)$company->id];
            $this->update($company->id, ['status' => 'ready', 'archive_id' => $id, 'archive_path' => $vaultPath,
                'archive_sha256' => hash_file('sha256', $zipPath), 'summary' => json_encode($summary), 'actor_id' => $actor,
                'verified_at' => null, 'downloaded_at' => null]);
            $armed = false;
            CompanyArchiveLog::step('archive_ready',['rows'=>array_sum(array_column($manifest['tables'],'count')),'total_files'=>count($manifest['files']),'bytes'=>filesize($zipPath)]);
            return $summary;
        } catch (\Throwable $e) {
            // Never overwrite the state of another build if our freeze/transition was rejected.
            if ($building) {
                try { self::recordBuildProgress((int)$company->id,$attempt,$stage,$detail,'BUILD_FAILED'); } catch (\Throwable) {}
                // This request caught the failure and has not removed any business data.
                // Resume only if the locked group still belongs to this exact failed attempt.
                try { CompanyArchiveScope::resumeFailedPreparation((int)$company->id,$attempt); }
                catch (\Throwable) { /* Failed recovery stays closed for operator review. */ }
            }
            throw $e;
        } finally { $armed = false; $reserve = null; $this->package->cleanup($work); }
    }

    private static function recordBuildProgress(int $companyId, string $attempt, string $stage, ?string $detail = null, ?string $error = null): void
    {
        $metadata = [];
        if ($stage === 'exporting_records' && $detail !== null) $metadata['table'] = $detail;
        if ($stage === 'copying_images' && $detail !== null) $metadata['file_index'] = (int)$detail;
        if ($error !== null) $metadata['error_code'] = $error;
        CompanyArchiveLog::step($stage,$metadata);
        DB::transaction(function () use ($companyId,$attempt,$stage,$detail,$error) {
            $state = DB::table('company_archive_states')->where('company_id',$companyId)->lockForUpdate()->first();
            if (!$state || $state->status !== 'building') return;
            $summary = json_decode($state->summary ?? '{}',true) ?: [];
            if (isset($summary['progress']['attempt']) && $summary['progress']['attempt'] !== $attempt) return;
            $summary['progress'] = ['attempt'=>$attempt,'stage'=>$stage,'detail'=>$detail,'error_code'=>$error,'updated_at'=>now()->toIso8601String(),
                'php_version'=>PHP_VERSION,'memory_limit'=>(string)ini_get('memory_limit'),'max_execution_time'=>(int)ini_get('max_execution_time')];
            DB::table('company_archive_states')->where('company_id',$companyId)->update(['summary'=>json_encode($summary,JSON_THROW_ON_ERROR),'updated_at'=>now()]);
        });
    }

    private function isLocalFile(array $file): bool
    {
        return $file['disk'] === 'public-root' || config('filesystems.disks.'.$file['disk'].'.driver') === 'local';
    }

    private function preserveRemoteOriginal(array $file): bool
    {
        // Cloud originals remain available for path-only restores, including when removing an older ZIP.
        // Keep this runtime-only so existing signed archives retain their policy hash.
        return !$this->isLocalFile($file);
    }

    public function validated(Company $company, string $path, string $password): array
    {
        $this->inventory->refreshMetadata();
        $manifest = $this->package->inspect($path, $password);
        CompanyArchiveGate::check(($manifest['purpose'] ?? 'archive')==='archive' && $manifest['version']!==6,
            'This is a copy-only backup. It cannot remove records or restore automatically. Review unresolved links before a separate recovery.');
        $state = CompanyArchiveGate::state($company->id);
        CompanyArchiveGate::check($state && $manifest['company_id'] === (int) $company->id && $manifest['company_slug'] === $company->slug
            && $state->archive_id === $manifest['archive_id'] && hash_equals($state->archive_sha256 ?? '', hash_file('sha256', $path)),
            'This is not the current archive for this company.');
        // Older signed ZIPs retain Laravel's original per-table FK ordering in their fingerprint.
        $schemaMatches = $manifest['schema'] === $this->inventory->schema();
        if (!$schemaMatches) $schemaMatches = $manifest['schema'] === $this->inventory->schema(true);
        CompanyArchiveGate::check($schemaMatches
            && $manifest['policy'] === hash('sha256', json_encode(config('company_archive'))),
            'Database/archive policy changed. A reviewed compatibility conversion is required; nothing was imported or removed.');
        CompanyArchiveScope::assertMembership($company,$manifest);
        if (in_array($manifest['version'],[3,4,5],true)) {
            $staff = app(CompanyArchiveUsers::class);
            $staff->assertAccess($manifest['staff']['access']);
            $this->assertStaffTransactional();
        }
        return $manifest;
    }

    public function verify(Company $company, string $path, string $password): array
    {
        $this->inventory->refreshMetadata();
        return CompanyArchiveLog::run('verify',(int)$company->id,fn()=>$this->verifyArchive($company,$path,$password));
    }

    private function verifyArchive(Company $company, string $path, string $password): array
    {
        CompanyArchiveLog::archive(CompanyArchiveGate::state($company->id)->archive_id ?? null);
        CompanyArchiveLog::step('locking_verification');
        $state = $this->transition($company->id, ['ready', 'verified'], 'verifying');
        try {
            CompanyArchiveLog::step('validating_saved_zip');
            $manifest = $this->validated($company, $path, $password);
            CompanyArchiveLog::step('reconciling_snapshot');
            $this->assertUnchanged($company, $manifest);
            $this->update($company->id, ['status' => 'verified', 'verified_at' => now()]);
            return $manifest;
        } catch (\Throwable $e) { $this->update($company->id, ['status' => $state->status]); throw $e; }
    }

    private function assertUnchanged(Company $company, array $manifest): void
    {
        CompanyArchiveScope::assertMembership($company,$manifest);
        $ids=CompanyArchiveScope::manifestIds((int)$company->id,$manifest);
        foreach ($manifest['order'] as $table) {
            $actual = $this->inventory->digest($table, $ids); $expected = $manifest['tables'][$table];
            CompanyArchiveGate::check($actual['count'] === $expected['count'] && hash_equals($actual['sha256'], $expected['sha256']),
                'Company data changed after export ('.$table.'). Cancel and create a fresh archive.');
        }
        if (isset($manifest['staff'])) {
            $staff = app(CompanyArchiveUsers::class); $staffIds = $manifest['staff']['ids'];
            CompanyArchiveGate::check($staff->ids($ids) === $staffIds && $staff->links($ids, $staffIds, $manifest['order']) === $manifest['staff']['links'], 'Staff account ownership or retained references changed after export. Cancel and create a fresh archive.');
            foreach ($this->staffTables($manifest) as $table) {
                $actual = $staff->digest($table, $staffIds); $expected = $manifest['tables'][$table];
                CompanyArchiveGate::check($actual['count'] === $expected['count'] && hash_equals($actual['sha256'], $expected['sha256']), 'Staff data changed after export ('.$table.'). Cancel and create a fresh archive.');
            }
        }
        $refs = $this->inventory->fileReferences($ids, $manifest['staff']['ids'] ?? null);
        $expectedFiles = array_merge($manifest['files'],$manifest['reference_files'] ?? [],array_map(fn($missing)=>$missing['reference'],$manifest['missing_optional_files'] ?? []));
        $actualKeys=array_keys($refs); $expectedKeys=array_keys($expectedFiles); sort($actualKeys); sort($expectedKeys);
        CompanyArchiveGate::check($actualKeys === $expectedKeys, 'Attachment references changed after export.');
        $this->assertCloudReferences($refs,$manifest);
        $this->assertOptionalReferences($refs,$manifest);
        foreach ($manifest['files'] as $file) {
            CompanyArchiveGate::check($this->fileHash($file) === $file['sha256'], 'An attachment changed after export.');
        }
    }

    private function fileHash(array $file): ?string
    {
        $disk = CompanyArchiveInventory::disk($file['disk']);
        if (!$disk->exists($file['path'])) return null;
        $stream = $disk->readStream($file['path']);
        CompanyArchiveGate::check(is_resource($stream), 'Cannot read attachment.');
        try { $hash = hash_init('sha256'); hash_update_stream($hash, $stream); return hash_final($hash); }
        finally { fclose($stream); }
    }

    private function assertCloudReferences(array $refs, array $manifest): void
    {
        if (!array_key_exists('reference_files',$manifest)) return;
        $actual = array_filter($refs,fn($file)=>!$this->isLocalFile($file));
        CompanyArchiveGate::check($actual == $manifest['reference_files'], 'Cloud image paths or storage identity changed. Restore requires the original cloud storage configuration; no cloud files were copied or overwritten.');
    }

    private function assertOptionalReferences(array $refs, array $manifest): void
    {
        foreach ($manifest['missing_optional_files'] ?? [] as $key=>$missing) {
            CompanyArchiveGate::check(isset($refs[$key]) && $refs[$key] == $missing['reference'] && $this->isLocalFile($refs[$key])
                && $this->inventory->isOptionalImageAttachment($refs[$key]), 'An omitted optional image reference changed or is now required. Nothing was overwritten.');
        }
    }

    public function remove(Company $company, string $path, string $password): void
    {
        $this->inventory->refreshMetadata();
        CompanyArchiveLog::run('remove',(int)$company->id,fn()=>$this->removeArchive($company,$path,$password));
    }

    private function removeArchive(Company $company, string $path, string $password): void
    {
        CompanyArchiveLog::archive(CompanyArchiveGate::state($company->id)->archive_id ?? null);
        CompanyArchiveLog::step('locking_removal');
        $before = $this->transition($company->id, ['verified', 'cleanup_pending'], 'removing');
        $databaseRemoved = $before->status === 'cleanup_pending';
        try {
            CompanyArchiveLog::step('validating_saved_zip');
            $manifest = $this->validated($company, $path, $password);
            $ids=CompanyArchiveScope::manifestIds((int)$company->id,$manifest);
            $this->queueIdle($ids); $this->inventory->assertTransactional($ids);
            if (!$databaseRemoved) {
                CompanyArchiveGate::check($before->verified_at && $before->downloaded_at, 'Download and verify your saved copy before removing records.');
                DB::transaction(function () use ($company, $manifest, $ids) {
                    CompanyArchiveLog::step('reconciling_snapshot');
                    $this->assertUnchanged($company, $manifest);
                    $this->inventory->assertNoForeignDependants($ids, $manifest['order']);
                    // Delete only declared tenant rows, child first; foreign-key checks stay enabled.
                    foreach (array_reverse($manifest['order']) as $table) {
                        CompanyArchiveLog::step('removing_table',['table'=>$table]);
                        $this->inventory->query($table, $ids)->delete();
                    }
                    if (isset($manifest['staff'])) {
                        $staff = app(CompanyArchiveUsers::class); $staffIds = $manifest['staff']['ids'];
                        $staff->detach($manifest['staff']['links']); $staff->revoke($staffIds);
                        foreach (array_reverse($this->staffTables($manifest)) as $table) $staff->query($table, $staffIds)->delete();
                    }
                    $opening = [];
                    foreach ($this->inventory->columns('companies') as $column) if (str_starts_with($column, 'opening_')) $opening[$column] = $column === 'opening_balance_date' ? null : 0;
                    if ($opening) DB::table('companies')->whereIn('id', $ids)->update($opening);
                    $this->update($company->id, ['status' => 'cleanup_pending']);
                });
                $databaseRemoved = true;
                CompanyArchiveLog::step('database_removal_committed');
            }
            // Re-check live references so a file newly shared by another company is never removed.
            CompanyArchiveLog::step('cleaning_unshared_attachments');
            $remaining = $this->inventory->fileReferences(null);
            foreach ($manifest['files'] as $key => $file) {
                if ($file['shared'] || isset($remaining[$key]) || $this->preserveRemoteOriginal($file)) continue;
                $disk = CompanyArchiveInventory::disk($file['disk']);
                if (!$disk->exists($file['path'])) continue;
                CompanyArchiveGate::check($this->fileHash($file) === $file['sha256'], 'Attachment changed during cleanup. Review it before retrying.');
                CompanyArchiveGate::check($disk->delete($file['path']), 'File cleanup is incomplete. Retry cleanup using the same archive.');
            }
            // Generated PDF image cache is not authoritative data; evict only this company's keys.
            $this->forgetImageCache($manifest, $path, $password);
            if ($before->archive_path) CompanyArchiveGate::check(Storage::disk(config('company_archive.disk'))->delete($before->archive_path), 'Encrypted server copy cleanup failed. Retry cleanup.');
            $this->update($company->id, ['status' => 'archived', 'archive_path' => null]);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            $this->update($company->id, ['status' => $databaseRemoved ? 'cleanup_pending' : 'verified']); throw $e;
        }
    }

    public function restore(Company $company, string $path, string $password): void
    {
        $this->inventory->refreshMetadata();
        CompanyArchiveLog::run('restore',(int)$company->id,fn()=>$this->restoreArchive($company,$path,$password));
    }

    private function restoreArchive(Company $company, string $path, string $password): void
    {
        CompanyArchiveLog::archive(CompanyArchiveGate::state($company->id)->archive_id ?? null);
        CompanyArchiveLog::step('locking_restore');
        $before = $this->transition($company->id, ['archived', 'cleanup_pending'], 'restoring');
        $created = [];
        try {
            CompanyArchiveLog::step('validating_saved_zip');
            $manifest = $this->validated($company, $path, $password);
            $ids=CompanyArchiveScope::manifestIds((int)$company->id,$manifest); $this->queueIdle($ids);
            foreach ($manifest['order'] as $table) CompanyArchiveGate::check(!$this->inventory->query($table, $ids)->exists(), 'Company/branch already has business records. Restore will not merge or overwrite them.');
            if (isset($manifest['staff'])) {
                foreach ($this->staffTables($manifest) as $table) CompanyArchiveGate::check(!app(CompanyArchiveUsers::class)->query($table, $manifest['staff']['ids'])->exists(), 'Staff account IDs or assignments already exist. Restore will not overwrite them.');
                CompanyArchiveGate::check(app(CompanyArchiveUsers::class)->ids($ids) === [], 'Company already has staff accounts. Restore will not merge them.');
            }
            $fileIndex = 0;
            foreach ($manifest['files'] as $file) {
                CompanyArchiveLog::step('restoring_image',['file_index'=>++$fileIndex,'total_files'=>count($manifest['files'])]);
                $disk = CompanyArchiveInventory::disk($file['disk']);
                if ($disk->exists($file['path'])) {
                    CompanyArchiveGate::check($this->fileHash($file) === $file['sha256'], 'A destination file has different contents. Restore stopped without overwriting it.'); continue;
                }
                $this->package->stream($path, $password, $file['entry'], function ($stream) use ($disk, $file) {
                    CompanyArchiveGate::check($disk->put($file['path'], $stream), 'Cannot restore an attachment.');
                });
                $created[] = $file;
                CompanyArchiveGate::check($this->fileHash($file) === $file['sha256'], 'Restored attachment verification failed.');
            }
            DB::transaction(function () use ($company, $path, $password, $manifest, $ids) {
                foreach (array_merge($this->staffTables($manifest), $manifest['order']) as $table) {
                    CompanyArchiveLog::step('restoring_table',['table'=>$table]);
                    $meta = $manifest['tables'][$table]; $count = 0;
                    $this->rows($path, $password, $meta['entry'], function ($row) use ($table, &$count) {
                        CompanyArchiveGate::check(array_keys($row) === $this->inventory->columns($table), 'Archive columns do not match '.$table.'.');
                        DB::table($table)->insert($row); $count++;
                    });
                    CompanyArchiveGate::check($count === $meta['count'], 'Archive row count mismatch.');
                }
                // Account/security/plan settings remain live; only archived opening values return.
                if (isset($manifest['tables']['companies'])) $this->rows($path, $password, $manifest['tables']['companies']['entry'], function ($row) use ($ids) {
                    CompanyArchiveGate::check(in_array((int)$row['id'],$ids,true),'Opening balances belong to an unexpected company.');
                    $opening = array_filter($row, fn ($key) => str_starts_with($key, 'opening_'), ARRAY_FILTER_USE_KEY);
                    if ($opening) DB::table('companies')->where('id', $row['id'])->update($opening);
                });
                if (isset($manifest['staff'])) app(CompanyArchiveUsers::class)->restoreLinks($manifest['staff']['links']);
                CompanyArchiveLog::step('reconciling_restored_records');
                foreach ($manifest['order'] as $table) {
                    $actual = $this->inventory->digest($table, $ids); $expected = $manifest['tables'][$table];
                    CompanyArchiveGate::check($actual['count'] === $expected['count'] && hash_equals($actual['sha256'], $expected['sha256']), 'Restored records did not reconcile: '.$table);
                }
                foreach ($this->staffTables($manifest) as $table) {
                    $actual = app(CompanyArchiveUsers::class)->digest($table, $manifest['staff']['ids']); $expected = $manifest['tables'][$table];
                    CompanyArchiveGate::check($actual['count'] === $expected['count'] && hash_equals($actual['sha256'], $expected['sha256']), 'Restored staff records did not reconcile: '.$table);
                }
                $restoredRefs = $this->inventory->fileReferences($ids,$manifest['staff']['ids'] ?? null);
                $this->assertCloudReferences($restoredRefs,$manifest);
                $this->assertOptionalReferences($restoredRefs,$manifest);
                $this->update($company->id, ['status' => 'active', 'archive_id' => null, 'archive_sha256' => null,
                    'archive_path' => null, 'verified_at' => null, 'downloaded_at' => null, 'summary' => null]);
            });
            if ($before->archive_path) Storage::disk(config('company_archive.disk'))->delete($before->archive_path);
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        } catch (\Throwable $e) {
            // Compensate for file writes if the database transaction failed. A crash stays paused for operator review.
            CompanyArchiveLog::step('compensating_failed_restore');
            $referenced = $this->inventory->fileReferences(null);
            foreach ($created as $file) {
                if ($this->preserveRemoteOriginal($file)) continue;
                $stillNeeded = collect($referenced)->contains(fn ($ref) => $ref['disk'] === $file['disk'] && $ref['path'] === $file['path']);
                if (!$stillNeeded && $this->fileHash($file) === $file['sha256']) CompanyArchiveInventory::disk($file['disk'])->delete($file['path']);
            }
            $this->update($company->id, ['status' => $before->status]); throw $e;
        }
    }

    private function rows(string $path, string $password, string $entry, callable $consume): void
    {
        $this->package->stream($path, $password, $entry, function ($stream) use ($consume) {
            while (($line = fgets($stream, 16 * 1024 * 1024)) !== false) {
                CompanyArchiveGate::check(str_ends_with($line, "\n"), 'Archive row is invalid or too large.');
                $row = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
                $consume(CompanyArchiveInventory::decodeRow($row));
            }
        });
    }

    private function staffTables(array $manifest): array
    {
        if (!isset($manifest['staff'])) return [];
        return array_values(array_intersect(CompanyArchiveUsers::TABLES, array_keys($manifest['tables'])));
    }

    private function assertStaffTransactional(): void
    {
        if (DB::getDriverName() !== 'mysql') return;
        foreach (Schema::getTables(DB::getDatabaseName()) as $table) {
            if (in_array($table['name'], array_merge(config('company_archive.retained'), ['sessions', 'password_set_tokens', 'password_reset_tokens', 'personal_access_tokens']), true)) {
                CompanyArchiveGate::check(strtolower($table['engine'] ?? '') === 'innodb', 'Staff archive needs transactional InnoDB storage: '.$table['name']);
            }
        }
    }

    private function forgetImageCache(array $manifest, string $path, string $password): void
    {
        if (!isset($manifest['tables']['item_sets'])) return;
        $this->rows($path, $password, $manifest['tables']['item_sets']['entry'], function ($row) {
            $key = 'stock-pdf-v1-'.hash('sha256', json_encode([(int)$row['company_id'], (int)$row['id'], $row['image_disk'] ?: config('filesystems.item_image_disk', 'public'),
                $row['image_path'], $row['image_mime'] ?? null, $row['updated_at'] ?? '']));
            Cache::store('file')->forget($key);
        });
    }

    public function cancel(Company $company): void
    {
        $this->inventory->refreshMetadata();
        CompanyArchiveLog::run('cancel',(int)$company->id,fn()=>$this->cancelArchive($company));
    }

    private function cancelArchive(Company $company): void
    {
        CompanyArchiveLog::archive(CompanyArchiveGate::state($company->id)->archive_id ?? null);
        CompanyArchiveLog::step('locking_cancellation');
        $state = $this->transition($company->id, ['preparing', 'ready', 'verified'], 'cancelling');
        try {
            if ($state->archive_path) CompanyArchiveGate::check(Storage::disk(config('company_archive.disk'))->delete($state->archive_path), 'Cannot delete the temporary encrypted archive. Retry cancellation.');
            $this->update($company->id, ['status' => 'active', 'archive_path' => null, 'archive_id' => null, 'archive_sha256' => null,
                'verified_at' => null, 'downloaded_at' => null, 'summary' => null]);
        } catch (\Throwable $e) { $this->update($company->id, ['status' => $state->status]); throw $e; }
    }
}
