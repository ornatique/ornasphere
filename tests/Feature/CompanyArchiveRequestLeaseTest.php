<?php

namespace Tests\Feature;

use App\Services\CompanyArchiveGate;
use App\Services\CompanyArchiveRequestLeases;
use App\Services\CompanyArchiveScope;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class CompanyArchiveRequestLeaseTest extends TestCase
{
    private string $privateStorage;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite',config('database.default'));
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        $this->privateStorage=storage_path('framework/testing/request-leases-'.Str::uuid());
        File::ensureDirectoryExists($this->privateStorage);
        $this->app->useStoragePath($this->privateStorage);
        $this->migrateControls();
    }

    private function migrateControls(): void
    {
        (require database_path('migrations/2026_10_31_120000_create_company_archive_states_table.php'))->up();
        (require database_path('migrations/2026_11_21_230000_create_company_archive_request_leases_table.php'))->up();
    }

    protected function tearDown(): void
    {
        foreach (DB::table('company_archive_states')->pluck('company_id') as $id) {
            for ($i=0;$i<4;$i++) CompanyArchiveGate::leave((int)$id);
        }
        DB::disconnect();
        parent::tearDown();
    }

    private function persistentFixture(): string
    {
        $database=$this->privateStorage.'/fixture.sqlite'; touch($database);
        DB::purge('sqlite'); config(['database.connections.sqlite.database'=>$database]);
        $this->migrateControls();
        return $database;
    }

    private function child(string $database, bool $hold = false): Process
    {
        $base=base_path();
        $code='require '.var_export($base.'/vendor/autoload.php',true).'; $app=require '.var_export($base.'/bootstrap/app.php',true).'; '
            .'$app->useStoragePath('.var_export($this->privateStorage,true).'); $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            .'App\\Services\\CompanyArchiveGate::enter(1); '
            .'Illuminate\\Support\\Facades\\DB::table("company_archive_request_leases")->update(["created_at"=>"2000-01-01 00:00:00"]); '
            .'echo "READY\n"; flush(); '.($hold ? 'while(true) usleep(20000);' : 'exit(0);');
        return new Process([PHP_BINARY,'-d','xdebug.mode=off','-d','xdebug.log=','-r',$code],$base,[
            'APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'DB_URL'=>'',
            'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','LOG_CHANNEL'=>'null',
            'LARAVEL_STORAGE_PATH'=>$this->privateStorage,
        ],null,15);
    }

    private function reconcile(int $id = 1): object
    {
        return DB::transaction(function () use ($id) {
            $state=DB::table('company_archive_states')->where('company_id',$id)->lockForUpdate()->first();
            CompanyArchiveGate::reconcile($state);
            return $state;
        });
    }

    public function test_normal_release_is_exactly_once_and_keeps_untracked_counters(): void
    {
        CompanyArchiveGate::ensure(1);
        DB::table('company_archive_states')->where('company_id',1)->update(['active_requests'=>3]);
        $this->assertTrue(CompanyArchiveGate::enter(1));
        $this->assertSame(4,(int)$this->reconcile()->active_requests);
        CompanyArchiveGate::leave(1); CompanyArchiveGate::leave(1);
        $this->assertSame(3,(int)$this->reconcile()->active_requests);
        $this->assertSame(0,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
    }

    public function test_terminated_request_is_cleared_without_touching_unknown_or_other_company_counts(): void
    {
        $database=$this->persistentFixture();
        CompanyArchiveGate::ensure(1); CompanyArchiveGate::ensure(2);
        DB::table('company_archive_states')->where('company_id',1)->update(['active_requests'=>3]);
        DB::table('company_archive_states')->where('company_id',2)->update(['active_requests'=>7]);
        $other=(array)CompanyArchiveGate::state(2);
        $child=$this->child($database); $child->run();
        $this->assertSame(0,$child->getExitCode(),$child->getErrorOutput());
        $this->assertSame(4,(int)CompanyArchiveGate::state(1)->active_requests);
        $this->assertSame(3,(int)$this->reconcile()->active_requests);
        $this->assertSame(0,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
        $this->assertSame($other,(array)CompanyArchiveGate::state(2));
    }

    public function test_even_very_old_live_request_stays_counted_until_its_process_stops(): void
    {
        $database=$this->persistentFixture(); $child=$this->child($database,true);
        try {
            $child->start();
            $this->assertTrue($child->waitUntil(fn($type,$output)=>str_contains($output,'READY')),$child->getErrorOutput());
            $this->assertSame(1,(int)$this->reconcile()->active_requests);
            $this->assertSame(1,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
            $this->assertTrue(CompanyArchiveGate::enter(1));
            $this->assertSame(2,(int)$this->reconcile()->active_requests);
            CompanyArchiveGate::leave(1);
            $this->assertSame(1,(int)$this->reconcile()->active_requests);
        } finally { $child->stop(0); }
        $this->assertSame(0,(int)$this->reconcile()->active_requests);
        $this->assertSame(0,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
    }

    public function test_missing_replaced_tampered_or_other_server_proof_is_never_auto_cleared(): void
    {
        $database=$this->persistentFixture(); $child=$this->child($database); $child->mustRun();
        $row=DB::table(CompanyArchiveRequestLeases::TABLE)->first();
        $path=$this->privateStorage.'/app/private/company-archive-request-leases/'.$row->lease_id.'.lock';
        $original=file_get_contents($path);
        file_put_contents($path,'changed-proof');
        $this->assertSame(1,(int)$this->reconcile()->active_requests);
        file_put_contents($path,$original);
        DB::table(CompanyArchiveRequestLeases::TABLE)->where('lease_id',$row->lease_id)->update(['owner'=>str_repeat('0',64)]);
        $this->assertSame(1,(int)$this->reconcile()->active_requests);
        DB::table(CompanyArchiveRequestLeases::TABLE)->where('lease_id',$row->lease_id)->update(['owner'=>$row->owner]);
        unlink($path);
        $this->assertSame(1,(int)$this->reconcile()->active_requests);
        file_put_contents($path,$original);
        $this->assertSame(1,(int)$this->reconcile()->active_requests); // Replacement inode is not the original lock.
    }

    public function test_archive_transition_reaps_dead_requests_only_after_live_requests_finish(): void
    {
        $database=$this->persistentFixture(); $dead=$this->child($database); $dead->mustRun();
        $live=$this->child($database,true);
        try {
            $live->start(); $this->assertTrue($live->waitUntil(fn($type,$output)=>str_contains($output,'READY')));
            DB::table('company_archive_states')->where('company_id',1)->update(['status'=>'preparing','summary'=>'{"group_root_id":1}']);
            try {
                CompanyArchiveScope::transition(1,['preparing'],'building');
                $this->fail('A live request must block backup.');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertStringContainsString('requests are still finishing',implode(' ',array_merge(...array_values($e->errors()))));
            }
            $this->assertSame('preparing',CompanyArchiveGate::state(1)->status);
            $this->assertSame(2,(int)CompanyArchiveGate::state(1)->active_requests);
            $this->assertSame(2,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
            $file=$this->privateStorage.'/logs/company-archive-'.date('Y-m-d').'.log';
            $this->assertStringNotContainsString('stale_request_cleared',file_get_contents($file));
        } finally { $live->stop(0); }
        CompanyArchiveScope::transition(1,['preparing'],'building');
        $this->assertSame('building',CompanyArchiveGate::state(1)->status);
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
        $this->assertSame(0,DB::table(CompanyArchiveRequestLeases::TABLE)->count());
    }

    public function test_counter_mismatch_fails_closed_instead_of_allowing_archive(): void
    {
        $this->assertTrue(CompanyArchiveGate::enter(1));
        DB::table('company_archive_states')->where('company_id',1)->update(['active_requests'=>0]);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->reconcile();
    }
}
