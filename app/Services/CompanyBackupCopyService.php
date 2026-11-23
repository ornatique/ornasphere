<?php

namespace App\Services;

use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Non-destructive, encrypted export. Never updates archive controls or business rows. */
class CompanyBackupCopyService
{
    public function __construct(private CompanyArchiveInventory $inventory, private CompanyArchivePackage $package) {}

    public function create(Company $company, string $password): array
    {
        return CompanyArchiveLog::run('copy_backup',(int)$company->id,fn()=>$this->export($company,$password));
    }

    private function export(Company $company, string $password): array
    {
        CompanyArchiveGate::check((bool)config('company_archive.enabled'),'Backup tools are not enabled.');
        CompanyArchiveGate::check(strlen($password)>=12 && strlen($password)<=1024,'Choose a backup password of at least 12 characters.');
        $this->package->assertAvailable();
        $connection=DB::connection(); $driver=$connection->getDriverName();
        CompanyArchiveGate::check($connection->transactionLevel()===0,'Finish the current transaction before creating a backup copy.');
        CompanyArchiveGate::check(in_array($driver,['mysql','mariadb','sqlite'],true),'Copy-only backup requires MySQL, MariaDB or SQLite snapshot support.');
        $work=$this->package->workspace(); $sqliteReadOnly=null; $started=false;
        register_shutdown_function(function () use ($work) { $this->package->cleanup($work); });
        try {
            // A consistent read snapshot replaces pausing or mutating the company.
            if ($driver==='sqlite') {
                $sqliteReadOnly=(int)$connection->selectOne('PRAGMA query_only')->query_only;
                $connection->statement('PRAGMA query_only=ON');
            } else $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ, READ ONLY');
            $connection->beginTransaction(); $started=true;
            $company=Company::findOrFail($company->id);
            CompanyArchiveScope::stateMembers((int)$company->id);
            $members=CompanyArchiveScope::family($company); $ids=CompanyArchiveScope::ids($members);
            $states=$connection->table('company_archive_states')->whereIn('company_id',$ids)->get();
            foreach ($states as $state) CompanyArchiveGate::check(in_array($state->status,['active','preparing','ready','verified'],true),
                'A company archive is being modified or its records are already removed. Finish or restore that operation before making a copy.');
            $this->inventory->refreshMetadata(); $this->inventory->coverage();
            $this->inventory->assertTransactional($ids);
            $order=$this->inventory->order();
            $tables=array_values(array_unique(array_merge($order,array_intersect(config('company_archive.retained'),$this->inventory->tables()))));
            $userIds=$this->inventory->hasTable('users') ? $this->inventory->query('users',$ids)->orderBy('id')->pluck('id')->all() : [];
            $users=app(CompanyArchiveUsers::class);
            $id=(string)Str::uuid(); CompanyArchiveLog::archive($id);
            $manifest=['version'=>6,'purpose'=>'copy_only','archive_id'=>$id,'company_id'=>(int)$company->id,'company_slug'=>$company->slug,
                'created_at'=>now()->toIso8601String(),'companies'=>$members,'schema'=>$this->inventory->schema(),
                'policy'=>hash('sha256',json_encode(config('company_archive'))),'order'=>$order,'tables'=>[],'entries'=>[],
                'media_policy'=>'cloud_paths_only','files'=>[],'reference_files'=>[],'missing_optional_files'=>[],'missing_required_files'=>[],
                'access_references'=>$users->access($userIds),'shared_legacy'=>$this->inventory->sharedLegacy(),
                'restore_policy'=>'manual_review_required','dependency_warnings'=>$this->inventory->dependencyWarnings($ids,$order)];
            $sources=[]; $index=0; $total=0;
            foreach ($tables as $table) {
                CompanyArchiveLog::step('copying_table',['table'=>$table]);
                $entry='data/'.(++$index).'.jsonl'; $path=$work.'/'.$index.'.jsonl';
                $stream=fopen($path,'wb'); $count=0;
                CompanyArchiveGate::check(is_resource($stream),'Cannot write private backup data.');
                try {
                    foreach ($this->inventory->ordered($table,$ids)->cursor() as $row) {
                        // Business records, including invalid payment links, are verbatim.
                        // Live login/session fields are excluded from account snapshots.
                        if ($table==='users') $row=$users->row($table,$row);
                        $line=json_encode(CompanyArchiveInventory::encodeRow($row),JSON_THROW_ON_ERROR)."\n";
                        CompanyArchiveGate::check(fwrite($stream,$line)===strlen($line),'Cannot write private backup data.');
                        $count++; $total+=strlen($line);
                        CompanyArchiveGate::check($total<=config('company_archive.max_bytes'),'Company exceeds the configured backup size limit.');
                    }
                } finally { fclose($stream); @chmod($path,0600); }
                $meta=['size'=>filesize($path),'sha256'=>hash_file('sha256',$path)];
                $manifest['tables'][$table]=['entry'=>$entry,'count'=>$count,'mode'=>'copy',...$meta];
                $manifest['entries'][$entry]=$meta; $sources[$entry]=$path;
                CompanyArchiveLog::step('table_copied',['table'=>$table,'rows'=>$count,'bytes'=>$meta['size']]);
            }
            $refs=$this->inventory->fileReferences($ids,$userIds);
            foreach ($refs as $key=>$file) {
                $local=$file['disk']==='public-root' || config('filesystems.disks.'.$file['disk'].'.driver')==='local';
                if (!$local) { $manifest['reference_files'][$key]=$file; continue; }
                $disk=CompanyArchiveInventory::disk($file['disk']);
                if (!$disk->exists($file['path'])) {
                    $missingKey=$this->inventory->isOptionalImageAttachment($file) ? 'missing_optional_files' : 'missing_required_files';
                    $manifest[$missingKey][$key]=['reference'=>$file,'source'=>$this->inventory->attachmentSourceHint($file)];
                    continue;
                }
                $entry='files/'.(++$index).'.bin'; $path=$work.'/'.$index.'.bin';
                $size=$disk->size($file['path']);
                CompanyArchiveGate::check($total+$size<=config('company_archive.max_bytes'),'Company exceeds the configured backup size limit.');
                $input=$disk->readStream($file['path']); $output=fopen($path,'wb');
                if (!is_resource($input) || !is_resource($output)) {
                    if (is_resource($input)) fclose($input); if (is_resource($output)) fclose($output);
                    CompanyArchiveGate::check(false,'Cannot read a local backup attachment.');
                }
                try { $copied=stream_copy_to_stream($input,$output,$size+1); } finally { fclose($input);fclose($output);@chmod($path,0600); }
                $meta=['size'=>filesize($path),'sha256'=>hash_file('sha256',$path)];
                $check=$disk->readStream($file['path']);
                CompanyArchiveGate::check(is_resource($check),'A backup attachment changed. Retry the copy.');
                try { $hash=hash_init('sha256');$read=hash_update_stream($hash,$check);$actualHash=hash_final($hash); } finally { fclose($check); }
                CompanyArchiveGate::check($copied===$size && $read===$size && hash_equals($meta['sha256'],$actualHash),'A backup attachment changed. Retry the copy.');
                $manifest['files'][$key]=[...$file,'entry'=>$entry,...$meta];$manifest['entries'][$entry]=$meta;$sources[$entry]=$path;$total+=$size;
            }
            $connection->rollBack();$started=false;
            if ($driver==='sqlite') { $connection->statement('PRAGMA query_only='.$sqliteReadOnly);$sqliteReadOnly=null; }
            $path=$work.'/copy.zip'; $this->package->create($path,$password,$manifest,$sources);$this->package->inspect($path,$password);
            return ['path'=>$path,'work'=>$work,'filename'=>'company-copy-'.$company->slug.'-'.$id.'.zip',
                'records'=>array_sum(array_column($manifest['tables'],'count')),'dependency_warnings'=>count($manifest['dependency_warnings']),
                'missing_local_attachments'=>count($manifest['missing_optional_files'])+count($manifest['missing_required_files'])];
        } catch (\Throwable $e) { $this->package->cleanup($work);throw $e; }
        finally {
            if ($started && $connection->transactionLevel()>0) $connection->rollBack();
            if ($driver==='sqlite' && $sqliteReadOnly!==null) $connection->statement('PRAGMA query_only='.$sqliteReadOnly);
        }
    }
}
