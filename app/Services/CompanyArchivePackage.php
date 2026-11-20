<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ZipArchive;

/** AES-256 ZIP plus an application-authenticated manifest; never executes SQL from uploads. */
class CompanyArchivePackage
{
    public function assertAvailable(): void
    {
        CompanyArchiveGate::check(class_exists(ZipArchive::class) && ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256, true), 'PHP ZIP with AES-256 encryption is required.');
    }

    public function workspace(): string
    {
        $path = storage_path('app/private/company-archives/work/'.Str::uuid());
        File::makeDirectory($path, 0700, true, true);
        CompanyArchiveGate::check(is_dir($path) && is_writable($path), 'Private archive temporary storage is not writable. Ask the server administrator to check storage permissions and free space; company entries have not been paused.');
        return $path;
    }

    public function cleanup(string $path): void
    {
        $root = realpath(storage_path('app/private/company-archives/work'));
        $target = realpath($path);
        if ($root && $target && str_starts_with($target, $root.DIRECTORY_SEPARATOR) && dirname($target) === $root) File::deleteDirectory($target);
    }

    private function mac(array $manifest): string
    {
        $key = config('app.key');
        CompanyArchiveGate::check(is_string($key) && strlen($key) >= 24, 'Application archive signing key is unavailable.');
        return hash_hmac('sha256', json_encode($manifest, JSON_THROW_ON_ERROR), hash('sha256', 'company-archive-v1|'.$key, true));
    }

    public function create(string $path, string $password, array $manifest, array $sources): void
    {
        CompanyArchiveLog::step('zip_creation_started',['total_files'=>count($sources)]);
        $this->assertAvailable();
        $zip = new ZipArchive;
        CompanyArchiveGate::check($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'Cannot create archive.');
        try {
            $zip->setPassword($password);
            $entryIndex = 0;
            foreach ($sources as $entry => $source) {
                CompanyArchiveLog::step('adding_encrypted_zip_entry',['file_index'=>++$entryIndex,'total_files'=>count($sources)]);
                CompanyArchiveGate::check($zip->addFile($source, $entry), 'Cannot add an archive entry.');
                CompanyArchiveGate::check($zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password), 'Cannot encrypt archive entry.');
            }
            $signed = ['manifest' => $manifest, 'mac' => $this->mac($manifest)];
            CompanyArchiveGate::check($zip->addFromString('manifest.json', json_encode($signed, JSON_THROW_ON_ERROR)), 'Cannot create archive manifest.');
            CompanyArchiveGate::check($zip->setEncryptionName('manifest.json', ZipArchive::EM_AES_256, $password), 'Cannot encrypt archive manifest.');
        } finally {
            CompanyArchiveLog::step('finalizing_zip');
            CompanyArchiveGate::check($zip->close(), 'Cannot finalize archive; free disk space and retry.');
        }
        CompanyArchiveLog::step('zip_creation_finished',['bytes'=>filesize($path)]);
        @chmod($path, 0600);
    }

    public function inspect(string $path, string $password): array
    {
        CompanyArchiveLog::step('zip_inspection_started');
        CompanyArchiveGate::check(is_file($path) && filesize($path) <= config('company_archive.max_bytes'), 'Archive exceeds the configured size limit.');
        $zip = new ZipArchive;
        CompanyArchiveGate::check($zip->open($path, ZipArchive::RDONLY) === true, 'Invalid archive file.');
        try {
            $zip->setPassword($password);
            $manifestStat = $zip->statName('manifest.json');
            CompanyArchiveGate::check($manifestStat && ($manifestStat['encryption_method'] ?? null) === ZipArchive::EM_AES_256, 'Archive manifest is not encrypted.');
            $stream = @$zip->getStream('manifest.json');
            CompanyArchiveGate::check(is_resource($stream), 'Cannot open archive. Check the password and file.');
            try { $json = stream_get_contents($stream, 8 * 1024 * 1024 + 1); } finally { fclose($stream); }
            CompanyArchiveGate::check(strlen($json) <= 8 * 1024 * 1024, 'Archive manifest is too large.');
            $signed = json_decode($json, true);
            CompanyArchiveGate::check(is_array($signed) && isset($signed['manifest'], $signed['mac']), 'Invalid archive manifest.');
            $manifest = $signed['manifest'];
            CompanyArchiveGate::check(hash_equals($this->mac($manifest), (string) $signed['mac']), 'Archive is modified or belongs to a different ERP installation.');
            CompanyArchiveGate::check(in_array($manifest['version'] ?? null, [1,2,3,4,5], true), 'Unsupported archive format.');
            if (in_array($manifest['version'],[3,4,5],true)) CompanyArchiveGate::check(isset($manifest['staff']['ids'], $manifest['staff']['access'], $manifest['staff']['links']), 'Staff archive scope is missing.');
            if (in_array($manifest['version'],[4,5],true)) {
                CompanyArchiveGate::check(($manifest['media_policy'] ?? null)==='cloud_paths_only' && isset($manifest['reference_files']) && is_array($manifest['reference_files']), 'Cloud reference policy is missing.');
                CompanyArchiveGate::check(!array_intersect_key($manifest['reference_files'],$manifest['files'] ?? []), 'A cloud reference cannot also be a copied attachment.');
                foreach ($manifest['reference_files'] as $key=>$reference) {
                    CompanyArchiveGate::check((bool)preg_match('/^[a-f0-9]{64}$/D',(string)$key) && is_array($reference)
                        && isset($reference['disk'],$reference['path']) && is_string($reference['disk']) && is_string($reference['path'])
                        && !isset($reference['entry']), 'Invalid cloud file reference.');
                    CompanyArchiveInventory::safePath($reference['path']);
                }
            }
            if ($manifest['version']===5) {
                CompanyArchiveGate::check(isset($manifest['missing_optional_files']) && is_array($manifest['missing_optional_files']), 'Optional image inventory is missing.');
                CompanyArchiveGate::check(!array_intersect_key($manifest['missing_optional_files'],$manifest['files'] ?? [])
                    && !array_intersect_key($manifest['missing_optional_files'],$manifest['reference_files'] ?? []), 'Conflicting optional image entries.');
                foreach ($manifest['missing_optional_files'] as $key=>$missing) {
                    $reference = $missing['reference'] ?? null;
                    CompanyArchiveGate::check((bool)preg_match('/^[a-f0-9]{64}$/D',(string)$key) && is_array($reference)
                        && isset($reference['disk'],$reference['path']) && is_string($reference['disk']) && is_string($reference['path'])
                        && !isset($reference['entry']), 'Invalid optional image reference.');
                    CompanyArchiveInventory::safePath($reference['path']);
                }
            }
            if ($manifest['version']===2) CompanyArchiveGate::check(isset($manifest['companies']) && count($manifest['companies'])>1,'Group archive is missing its company/branch scope.');
            $entries = $manifest['entries'] ?? [];
            CompanyArchiveGate::check(count($entries) < 50000 && $zip->numFiles === count($entries) + 1, 'Unexpected archive entries.');
            $total = 0; $entryIndex = 0;
            foreach ($entries as $entry => $meta) {
                CompanyArchiveLog::step('checking_zip_entry',['file_index'=>++$entryIndex,'total_files'=>count($entries)]);
                CompanyArchiveGate::check((bool) preg_match('~^(data|files)/[0-9]+\.(jsonl|bin)$~D', $entry), 'Unsafe archive entry name.');
                $stat = $zip->statName($entry);
                CompanyArchiveGate::check($stat && $stat['size'] === $meta['size'] && ($stat['encryption_method'] ?? null) === ZipArchive::EM_AES_256, 'Missing, unencrypted or invalid archive entry.');
                $total += $stat['size'];
                CompanyArchiveGate::check($total <= config('company_archive.max_bytes'), 'Expanded archive exceeds the size limit.');
                $stream = @$zip->getStream($entry);
                CompanyArchiveGate::check(is_resource($stream), 'Cannot decrypt archive entry.');
                try {
                    $hash = hash_init('sha256'); $read = hash_update_stream($hash, $stream);
                    CompanyArchiveGate::check($read === $meta['size'] && hash_equals($meta['sha256'], hash_final($hash)), 'Archive checksum verification failed.');
                } finally { fclose($stream); }
            }
            CompanyArchiveLog::step('zip_inspection_finished',['total_files'=>count($entries),'bytes'=>$total]);
            return $manifest;
        } finally { $zip->close(); }
    }

    public function stream(string $path, string $password, string $entry, callable $read): mixed
    {
        $zip = new ZipArchive;
        CompanyArchiveGate::check($zip->open($path, ZipArchive::RDONLY) === true, 'Archive is unavailable.');
        try {
            $zip->setPassword($password); $stream = @$zip->getStream($entry);
            CompanyArchiveGate::check(is_resource($stream), 'Archive entry is unavailable.');
            try { return $read($stream); } finally { fclose($stream); }
        } finally { $zip->close(); }
    }
}
