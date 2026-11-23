<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyArchiveGate;
use App\Services\CompanyArchiveInventory;
use App\Services\CompanyArchivePackage;
use App\Services\CompanyArchiveService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CompanyArchiveTest extends TestCase
{
    private string $password = 'test-archive-password-123';
    private array $workspaces = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        if (!class_exists(\ZipArchive::class)) $this->markTestSkipped('ZIP required.');
        config(['company_archive.enabled' => true, 'company_archive.disk' => 'local', 'queue.default' => 'database', 'cache.stores.file' => ['driver' => 'array']]);
        config(['view.compiled'=>storage_path('framework/testing/archive-views')]);
        \Illuminate\Support\Facades\File::ensureDirectoryExists(config('view.compiled'));
        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('name'); $t->string('slug'); $t->boolean('status')->default(true);
            $t->unsignedBigInteger('parent_company_id')->nullable(); $t->date('opening_balance_date')->nullable();
            $t->decimal('opening_cash', 12, 2)->default(0); $t->string('company_logo')->nullable();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('name'); $t->string('email'); $t->string('password');
        });
        Schema::create('roles', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); $t->unsignedBigInteger('company_id')->nullable(); });
        Schema::create('model_has_roles', function (Blueprint $t) {
            $t->unsignedBigInteger('role_id'); $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->primary(['role_id', 'model_type', 'model_id']);
        });
        Schema::create('items', function (Blueprint $t) { $t->id(); $t->foreignId('company_id')->constrained(); $t->string('item_name'); });
        Schema::create('item_sets', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained(); $t->foreignId('item_id')->constrained();
            $t->string('image_disk')->default('public'); $t->string('image_path')->nullable();
            $t->string('image_mime')->nullable(); $t->timestamp('updated_at')->nullable();
        });
        Schema::create('customers', function (Blueprint $t) { $t->id(); $t->foreignId('company_id')->constrained(); $t->string('name'); });
        Schema::create('sales', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained(); $t->foreignId('customer_id')->constrained(); $t->decimal('net_total', 15, 2);
        });
        Schema::create('sale_items', function (Blueprint $t) {
            $t->id(); $t->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $t->foreignId('itemset_id')->constrained('item_sets'); $t->decimal('fine_weight', 15, 3);
        });
        (require database_path('migrations/2026_10_31_120000_create_company_archive_states_table.php'))->up();
        DB::table('companies')->insert([
            ['id'=>1,'name'=>'One','slug'=>'one','opening_cash'=>500,'opening_balance_date'=>'2026-10-01'],
            ['id'=>2,'name'=>'Two','slug'=>'two','opening_cash'=>600,'opening_balance_date'=>'2026-10-01'],
        ]);
        DB::table('users')->insert([
            ['id'=>1,'company_id'=>1,'name'=>'Admin','email'=>'one@example.test','password'=>Hash::make('account-password')],
            ['id'=>2,'company_id'=>2,'name'=>'Admin Two','email'=>'two@example.test','password'=>Hash::make('account-password')],
            ['id'=>3,'company_id'=>1,'name'=>'Staff','email'=>'staff@example.test','password'=>Hash::make('account-password')],
        ]);
        DB::table('roles')->insert(['id'=>1,'name'=>'company_admin','guard_name'=>'web','company_id'=>null]);
        DB::table('model_has_roles')->insert(['role_id'=>1,'model_type'=>User::class,'model_id'=>1]);
        DB::table('items')->insert([['id'=>1,'company_id'=>1,'item_name'=>'Ring'],['id'=>2,'company_id'=>2,'item_name'=>'Other ring']]);
        DB::table('item_sets')->insert([['id'=>1,'company_id'=>1,'item_id'=>1,'image_path'=>'item-images/one.png'],['id'=>2,'company_id'=>2,'item_id'=>2,'image_path'=>'item-images/two.png']]);
        DB::table('customers')->insert([['id'=>1,'company_id'=>1,'name'=>'Customer One'],['id'=>2,'company_id'=>2,'name'=>'Customer Two']]);
        DB::table('sales')->insert([['id'=>1,'company_id'=>1,'customer_id'=>1,'net_total'=>1234.56],['id'=>2,'company_id'=>2,'customer_id'=>2,'net_total'=>9876.54]]);
        DB::table('sale_items')->insert([['id'=>1,'sale_id'=>1,'itemset_id'=>1,'fine_weight'=>12.345],['id'=>2,'sale_id'=>2,'itemset_id'=>2,'fine_weight'=>54.321]]);
        Storage::fake('local'); Storage::fake('public');
        Storage::disk('public')->put('item-images/one.png', "private image\x00bytes");
        Storage::disk('public')->put('item-images/two.png', 'other company image');
    }

    protected function tearDown(): void
    {
        foreach ($this->workspaces as $work) app(CompanyArchivePackage::class)->cleanup($work);
        parent::tearDown();
    }

    private function company(): Company { return Company::findOrFail(1); }
    private function service(): CompanyArchiveService { return app(CompanyArchiveService::class); }

    private function prepared(): string
    {
        $this->service()->prepare($this->company(), 1, $this->password);
        $state = CompanyArchiveGate::state(1);
        $work = app(CompanyArchivePackage::class)->workspace(); $this->workspaces[] = $work;
        $path = $work.'/copy.zip'; file_put_contents($path, Storage::disk('local')->get($state->archive_path));
        DB::table('company_archive_states')->where('company_id',1)->update(['downloaded_at'=>now()]);
        return $path;
    }

    private function archived(): string
    {
        $path = $this->prepared(); $this->service()->verify($this->company(), $path, $this->password);
        $this->service()->remove($this->company(), $path, $this->password); return $path;
    }

    private function rejected(callable $action, string $contains = ''): void
    {
        try { $action(); $this->fail('Expected archive operation to be rejected.'); }
        catch (ValidationException $e) { if ($contains !== '') $this->assertStringContainsString($contains, implode(' ', $e->validator->errors()->all())); }
    }

    public function test_encrypted_round_trip_preserves_history_files_and_other_company(): void
    {
        $inventory = app(CompanyArchiveInventory::class);
        $before = [];
        foreach ($inventory->order() as $table) $before[$table] = $inventory->digest($table, 1);
        $other = DB::table('sales')->where('id',2)->first();
        $path = $this->prepared();
        $zip = new \ZipArchive; $zip->open($path); $this->assertFalse(@$zip->getFromName('manifest.json')); $zip->close();
        $this->assertSame(2, DB::table('sales')->count());
        $this->service()->verify($this->company(), $path, $this->password);
        $this->service()->remove($this->company(), $path, $this->password);
        foreach ($inventory->order() as $table) $this->assertSame(0, $inventory->query($table,1)->count());
        $this->assertSame('archived', CompanyArchiveGate::state(1)->status);
        $this->assertSame(2, DB::table('users')->count());
        $this->assertTrue(DB::table('users')->where('id',1)->exists());
        $this->assertFalse(DB::table('users')->where('id',3)->exists());
        $this->assertEquals(0, DB::table('companies')->where('id',1)->value('opening_cash'));
        Storage::disk('public')->assertMissing('item-images/one.png');
        $this->assertSame((array)$other, (array)DB::table('sales')->where('id',2)->first());
        $this->assertSame('other company image', Storage::disk('public')->get('item-images/two.png'));
        $this->service()->restore($this->company(), $path, $this->password);
        foreach ($inventory->order() as $table) $this->assertSame($before[$table], $inventory->digest($table,1));
        $this->assertEquals(500, DB::table('companies')->where('id',1)->value('opening_cash'));
        $this->assertSame("private image\x00bytes", Storage::disk('public')->get('item-images/one.png'));
        $this->assertSame('active', CompanyArchiveGate::state(1)->status);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_unknown_future_table_and_new_attachment_column_fail_closed(): void
    {
        Schema::create('new_feature_records', fn(Blueprint $t) => $t->id());
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password), 'Unreviewed database tables');
        Schema::drop('new_feature_records');
        Schema::table('items', fn(Blueprint $t) => $t->string('certificate_path')->nullable());
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password), 'Unreviewed file/reference');
        $this->assertSame(2, DB::table('sales')->count());
    }

    public function test_wrong_password_wrong_company_and_modified_zip_are_rejected(): void
    {
        $path = $this->prepared();
        $this->rejected(fn()=>$this->service()->verify($this->company(),$path,'wrong-password'), 'password');
        $this->assertSame('ready', CompanyArchiveGate::state(1)->status);
        $this->rejected(fn()=>$this->service()->validated(Company::find(2),$path,$this->password), 'current archive');
        $zip = new \ZipArchive; $zip->open($path); $zip->addFromString('../unexpected.php','bad'); $zip->close();
        $this->rejected(fn()=>$this->service()->verify($this->company(),$path,$this->password), 'Unexpected');
        $this->assertSame(2, DB::table('sales')->count());
    }

    public function test_changes_after_export_are_detected_and_cancel_preserves_current_records(): void
    {
        $path = $this->prepared(); DB::table('sales')->where('id',1)->update(['net_total'=>4321]);
        $this->rejected(fn()=>$this->service()->verify($this->company(),$path,$this->password), 'changed after export');
        $this->service()->cancel($this->company());
        $this->assertSame('active', CompanyArchiveGate::state(1)->status);
        $this->assertEquals(4321, DB::table('sales')->where('id',1)->value('net_total'));
        Storage::disk('public')->assertExists('item-images/one.png');
    }

    public function test_shared_image_is_kept_and_other_company_is_unchanged(): void
    {
        DB::table('item_sets')->where('id',2)->update(['image_path'=>'item-images/one.png']);
        $path = $this->archived();
        Storage::disk('public')->assertExists('item-images/one.png');
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(2, DB::table('sales')->count());
    }

    public function test_missing_optional_image_is_recorded_without_blocking_export_or_removing_records(): void
    {
        Storage::disk('public')->delete('item-images/one.png');
        $path=$this->prepared(); $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertCount(1,$manifest['missing_optional_files']); $this->assertCount(0,$manifest['files']);
        $this->assertSame('ready', CompanyArchiveGate::state(1)->status);
        $this->assertSame(2, DB::table('sales')->count());
    }

    public function test_restore_schema_change_and_file_conflict_do_not_overwrite_data(): void
    {
        $path = $this->archived();
        Storage::disk('public')->put('item-images/one.png','new conflicting file');
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password), 'different contents');
        $this->assertSame('new conflicting file', Storage::disk('public')->get('item-images/one.png'));
        Schema::table('sales', fn(Blueprint $t)=>$t->string('new_column')->nullable());
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password), 'compatibility');
        $this->assertSame('archived', CompanyArchiveGate::state(1)->status);
        $this->assertSame(1, DB::table('sales')->count());
    }

    public function test_restore_id_collision_rolls_back_and_removes_newly_written_files(): void
    {
        $path = $this->archived();
        DB::table('items')->insert(['id'=>1,'company_id'=>2,'item_name'=>'Conflicting other company item']);
        try { $this->service()->restore($this->company(),$path,$this->password); $this->fail('Expected ID collision.'); }
        catch (\Illuminate\Database\QueryException $e) { $this->assertSame('archived', CompanyArchiveGate::state(1)->status); }
        $this->assertSame(0, DB::table('sales')->where('company_id',1)->count());
        Storage::disk('public')->assertMissing('item-images/one.png');
        $this->assertSame('Conflicting other company item', DB::table('items')->where('id',1)->value('item_name'));
    }

    public function test_pending_requests_and_queue_prevent_snapshot_and_keep_other_company_running(): void
    {
        $this->assertTrue(CompanyArchiveGate::enter(1));
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password), 'requests are still finishing');
        $this->assertFalse(CompanyArchiveGate::enter(1));
        $this->assertTrue(CompanyArchiveGate::enter(2)); CompanyArchiveGate::leave(2); CompanyArchiveGate::leave(1);
        $this->service()->cancel($this->company());
        Schema::create('jobs', function(Blueprint $t) {$t->id(); $t->text('payload');});
        DB::table('jobs')->insert(['payload'=>'pending']);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password), 'jobs must finish');
    }

    public function test_cross_company_cascade_reference_blocks_archive(): void
    {
        DB::table('sale_items')->where('id',2)->update(['itemset_id'=>1]);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password), 'Other-company');
        $this->assertSame(2, DB::table('sale_items')->count());
    }

    public function test_remove_requires_verification_and_a_download_before_deleting(): void
    {
        $path = $this->prepared();
        $this->rejected(fn()=>$this->service()->remove($this->company(),$path,$this->password), 'not available');
        $this->service()->verify($this->company(),$path,$this->password);
        DB::table('company_archive_states')->where('company_id',1)->update(['downloaded_at'=>null]);
        $this->rejected(fn()=>$this->service()->remove($this->company(),$path,$this->password), 'Download and verify');
        $this->assertSame(2, DB::table('sales')->count());
    }

    public function test_company_admin_only_current_password_and_form_rendering(): void
    {
        $controller = app(\App\Http\Controllers\Company\CompanyArchiveController::class);
        $request = Request::create('/company/one/profile/archive/prepare','POST',['current_password'=>'account-password','password'=>$this->password,'password_confirmation'=>$this->password]);
        $request->setUserResolver(fn()=>User::find(3));
        try { $controller->prepare($request,'one',$this->service()); $this->fail('Staff must be denied.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $request->setUserResolver(fn()=>User::find(1));
        $request->merge(['current_password'=>'incorrect']);
        $this->rejected(fn()=>$controller->prepare($request,'one',$this->service()), 'account password');
        $html = view('company.profile.archive', ['company'=>$this->company(),'user'=>User::find(1)])->with('errors',new \Illuminate\Support\ViewErrorBag)->render();
        $this->assertStringContainsString('Data Backup',$html);
        $this->assertStringContainsString('password_confirmation',$html);
        $this->assertStringNotContainsString('value="'.$this->password.'"',$html);
        $this->assertStringNotContainsString('Data Backup',view('company.profile.archive',['company'=>$this->company(),'user'=>User::find(3)])->render());
    }

    public function test_http_gate_allows_archived_reading_but_blocks_writes_even_if_feature_is_disabled(): void
    {
        $this->archived(); config(['company_archive.enabled'=>false]);
        $middleware = new \App\Http\Middleware\CompanyArchiveAccess;
        $request = Request::create('/company/one/sales','GET',[],[],[],['HTTP_ACCEPT'=>'application/json']);
        $route = new \Illuminate\Routing\Route('GET','company/{slug}/sales',fn()=>null);
        $route->name('company.sales.index'); $route->bind($request);
        $request->setRouteResolver(fn()=>$route); $request->setUserResolver(fn()=>User::find(1));
        $called = false;
        $response = $middleware->handle($request,function()use(&$called){$called=true;return response('read-only list');});
        $this->assertSame(200,$response->getStatusCode()); $this->assertTrue($called);
        $this->assertSame('1',$response->headers->get('X-Company-Archive-Read-Only'));
        $request->setMethod('POST');$called=false;
        $this->assertSame(423,$middleware->handle($request,function()use(&$called){$called=true;return response('wrong');})->getStatusCode());
        $this->assertFalse($called);$request->setMethod('GET');
        $route->setAction([...$route->getAction(), 'as'=>'company.profile.show']);
        $this->assertSame('profile', $middleware->handle($request,fn()=>response('profile'))->getContent());
        $route->setAction([...$route->getAction(), 'as'=>'company.profile.archive.restore']);
        $this->assertSame('restore', $middleware->handle($request,fn()=>response('restore'))->getContent());
    }

    public function test_request_lease_logs_pair_on_exception_without_request_secrets(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/archive-lease-'.bin2hex(random_bytes(5)));
        $request=Request::create('/company/one/items?token=PRIVATE-QUERY-CANARY','POST',[
            'current_password'=>'PRIVATE-PASSWORD-CANARY',
        ]);
        $request->headers->set('Authorization','Bearer PRIVATE-TOKEN-CANARY');
        $route=new \Illuminate\Routing\Route('POST','company/{slug}/items',['as'=>'company.items.store','uses'=>fn()=>null]);
        $route->bind($request); $request->setRouteResolver(fn()=>$route);
        $this->app->instance('request',$request); $request->setUserResolver(fn()=>User::find(1));
        try {
            (new \App\Http\Middleware\CompanyArchiveAccess)->handle($request, function () {
                throw new \RuntimeException('PRIVATE-EXCEPTION-CANARY');
            });
            $this->fail('Expected request exception.');
        } catch (\RuntimeException $e) { $this->assertSame('PRIVATE-EXCEPTION-CANARY',$e->getMessage()); }
        $events=$this->archiveLogEvents();
        $this->assertSame(['request_admitted','request_released'],array_column($events,'event'));
        $this->assertSame([1,0],array_column($events,'active_requests'));
        $this->assertSame($events[0]['request_reference'],$events[1]['request_reference']);
        $this->assertTrue(\Illuminate\Support\Str::isUuid($events[0]['request_reference']));
        $this->assertSame('company.items.store',$events[0]['route_name']);
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
        $log=file_get_contents(storage_path('logs/company-archive-'.date('Y-m-d').'.log'));
        foreach (['PRIVATE-QUERY-CANARY','PRIVATE-PASSWORD-CANARY','PRIVATE-TOKEN-CANARY','PRIVATE-EXCEPTION-CANARY','one@example.test'] as $secret) $this->assertStringNotContainsString($secret,$log);
    }

    public function test_busy_group_logs_all_blocking_counters_without_clearing_them(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/archive-busy-'.bin2hex(random_bytes(5)));
        $this->branch(); CompanyArchiveGate::enter(1); CompanyArchiveGate::enter(3);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'requests are still finishing');
        $events=array_values(array_filter($this->archiveLogEvents(),fn($e)=>$e['event']==='requests_not_drained'));
        $this->assertSame([1,3],array_column($events,'company_id'));
        foreach ($events as $event) {
            $this->assertSame(1,$event['root_company_id']); $this->assertSame(1,$event['active_requests']);
            $this->assertSame('preparing',$event['state']); $this->assertSame('building',$event['target_state']);
            $this->assertTrue(\Illuminate\Support\Str::isUuid($event['reference']));
        }
        foreach ([1,3] as $id) {
            $this->assertSame(1,(int)CompanyArchiveGate::state($id)->active_requests);
            $this->assertSame('preparing',CompanyArchiveGate::state($id)->status);
            CompanyArchiveGate::leave($id);
        }
    }

    public function test_request_cleanup_is_once_only_and_never_clears_other_requests(): void
    {
        CompanyArchiveGate::ensure(1);
        DB::table('company_archive_states')->where('company_id',1)->update(['active_requests'=>2]);
        $leases=[]; $release=CompanyArchiveGate::protectRequests($leases);
        $this->assertTrue(CompanyArchiveGate::enter(1)); $leases[]=1;
        $this->assertSame(3,(int)CompanyArchiveGate::state(1)->active_requests);
        $release(); $release();
        $this->assertSame([],$leases);
        $this->assertSame(2,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_shutdown_releases_only_owned_counter_on_exit_fatal_and_abandoned_transaction(): void
    {
        foreach (['exit','fatal','transaction_fatal'] as $scenario) {
            $storage=storage_path('framework/testing/request-shutdown-'.\Illuminate\Support\Str::uuid());
            \Illuminate\Support\Facades\File::ensureDirectoryExists($storage);
            $database=$storage.'/fixture.sqlite'; touch($database);
            $base=base_path();
            $code='require '.var_export($base.'/vendor/autoload.php',true).'; $app=require '.var_export($base.'/bootstrap/app.php',true).'; '
                .'$app->useStoragePath('.var_export($storage,true).'); $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
                .'(require '.var_export($base.'/database/migrations/2026_10_31_120000_create_company_archive_states_table.php',true).')->up(); '
                .'Illuminate\\Support\\Facades\\Schema::create("items",function($t){$t->id(); $t->string("name");}); '
                .'Illuminate\\Support\\Facades\\DB::table("items")->insert(["name"=>"unchanged"]); '
                .'App\\Services\\CompanyArchiveGate::ensure(1); Illuminate\\Support\\Facades\\DB::table("company_archive_states")->where("company_id",1)->update(["active_requests"=>2]); '
                .'$leases=[]; $release=App\\Services\\CompanyArchiveGate::protectRequests($leases); App\\Services\\CompanyArchiveGate::enter(1); $leases[]=1; ';
            if ($scenario==='transaction_fatal') $code.='Illuminate\\Support\\Facades\\DB::beginTransaction(); Illuminate\\Support\\Facades\\DB::table("items")->insert(["name"=>"must roll back"]); ';
            $code.=$scenario==='exit' ? 'exit(0);' : 'set_error_handler(static fn()=>false); trigger_error("PRIVATE-FATAL-CANARY",E_USER_ERROR);';
            $process=new \Symfony\Component\Process\Process([PHP_BINARY,'-d','xdebug.mode=off','-d','xdebug.log=','-r',$code],$base,
                ['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>$database,'CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','LOG_CHANNEL'=>'null','LARAVEL_STORAGE_PATH'=>$storage]);
            $process->run();
            if ($scenario==='exit') $this->assertSame(0,$process->getExitCode(),$process->getErrorOutput());
            else $this->assertNotSame(0,$process->getExitCode());
            $pdo=new \PDO('sqlite:'.$database);
            $this->assertSame(2,(int)$pdo->query('SELECT active_requests FROM company_archive_states WHERE company_id=1')->fetchColumn(),$scenario);
            $this->assertSame(['unchanged'],$pdo->query('SELECT name FROM items')->fetchAll(\PDO::FETCH_COLUMN),$scenario);
            $log=file_get_contents($storage.'/logs/company-archive-'.date('Y-m-d').'.log');
            $this->assertStringContainsString('company_archive.request_recovered',$log);
            $this->assertStringNotContainsString('PRIVATE-FATAL-CANARY',$log);
        }
    }

    private function legacyVacuumFixture(): void
    {
        Schema::create('vacuum_buchs',function(Blueprint $t){$t->id();$t->unsignedBigInteger('company_id');});
        Schema::create('vacuum_vouchers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('company_id');});
        Schema::create('vacuum_voucher_items',function(Blueprint $t){
            $t->id();$t->unsignedBigInteger('vacuum_voucher_id');$t->unsignedBigInteger('vacuum_buch_id');$t->string('remarks');
        });
        DB::table('vacuum_buchs')->insert([['id'=>200,'company_id'=>1],['id'=>201,'company_id'=>2]]);
        DB::table('vacuum_vouchers')->insert([['id'=>300,'company_id'=>1],['id'=>301,'company_id'=>2]]);
        DB::table('vacuum_voucher_items')->insert([
            ['id'=>400,'vacuum_voucher_id'=>999,'vacuum_buch_id'=>200,'remarks'=>'Own legacy orphan'],
            ['id'=>401,'vacuum_voucher_id'=>1000,'vacuum_buch_id'=>201,'remarks'=>'Other legacy orphan'],
            ['id'=>402,'vacuum_voucher_id'=>300,'vacuum_buch_id'=>200,'remarks'=>'Own current item'],
            ['id'=>403,'vacuum_voucher_id'=>301,'vacuum_buch_id'=>201,'remarks'=>'Other current item'],
        ]);
    }

    public function test_missing_legacy_voucher_items_round_trip_verbatim_via_owned_buch(): void
    {
        $this->legacyVacuumFixture();
        $before=DB::table('vacuum_voucher_items')->orderBy('id')->get()->map(fn($r)=>(array)$r)->all();
        $this->assertSame([400,402],app(CompanyArchiveInventory::class)->query('vacuum_voucher_items',1)->orderBy('id')->pluck('id')->all());
        $path=$this->archived();
        $this->assertSame([401,403],DB::table('vacuum_voucher_items')->orderBy('id')->pluck('id')->all());
        $this->assertSame(1,DB::table('vacuum_buchs')->where('company_id',2)->count());
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame($before,DB::table('vacuum_voucher_items')->orderBy('id')->get()->map(fn($r)=>(array)$r)->all());
        $this->assertFalse(DB::table('vacuum_vouchers')->where('id',999)->exists());
    }

    public function test_existing_other_company_voucher_link_still_blocks_buch_archive(): void
    {
        $this->legacyVacuumFixture();
        DB::table('vacuum_voucher_items')->insert(['id'=>404,'vacuum_voucher_id'=>301,'vacuum_buch_id'=>200,'remarks'=>'Cross-company link must block']);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'Other-company or retained records reference vacuum_buchs');
        $this->assertSame(5,DB::table('vacuum_voucher_items')->count());
        $this->assertSame(2,DB::table('vacuum_buchs')->count());
    }

    public function test_existing_archive_remains_restorable_after_request_tracking_is_installed(): void
    {
        $path=$this->archived();
        $inventory=app(CompanyArchiveInventory::class); $before=$inventory->schema();
        $policy=hash('sha256',json_encode(config('company_archive')));
        (require database_path('migrations/2026_11_21_230000_create_company_archive_request_leases_table.php'))->up();
        $inventory->refreshMetadata();
        $this->assertSame($before,$inventory->schema());
        $this->assertSame($policy,hash('sha256',json_encode(config('company_archive'))));
        $this->assertNotContains('company_archive_request_leases',array_keys($inventory->schema()));
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);
        $this->assertSame(2,DB::table('sales')->count());
    }

    private function crossCompanyPaymentFixture(): array
    {
        Schema::create('sale_payments',function(Blueprint $t){
            $t->id();$t->foreignId('company_id')->constrained();$t->foreignId('sale_id')->constrained('sales');
            $t->decimal('amount',12,2);$t->date('paid_on');$t->string('payment_mode');$t->unsignedBigInteger('created_by');
        });
        DB::table('sale_payments')->insert([
            ['id'=>1,'company_id'=>1,'sale_id'=>2,'amount'=>2000,'paid_on'=>'2026-04-16','payment_mode'=>'cash','created_by'=>1],
            ['id'=>2,'company_id'=>1,'sale_id'=>2,'amount'=>1000,'paid_on'=>'2026-05-27','payment_mode'=>'online','created_by'=>1],
        ]);
        return DB::table('sale_payments')->orderBy('id')->get()->map(fn($r)=>CompanyArchiveInventory::encodeRow($r))->all();
    }

    private function databaseSnapshot(): array
    {
        $inventory=app(CompanyArchiveInventory::class);$inventory->refreshMetadata();$out=[];
        foreach ($inventory->tables() as $table) {
            $query=DB::table($table);foreach ($inventory->columns($table) as $column) $query->orderBy($column);
            $out[$table]=$query->get()->map(fn($r)=>(array)$r)->all();
        }
        return $out;
    }

    public function test_copy_only_preserves_invalid_payments_and_every_database_row(): void
    {
        $payments=$this->crossCompanyPaymentFixture();
        CompanyArchiveGate::ensure(1);
        DB::table('company_archive_states')->where('company_id',1)->update(['status'=>'preparing','active_requests'=>3]);
        $before=$this->databaseSnapshot();
        $copy=app(\App\Services\CompanyBackupCopyService::class)->create($this->company(),$this->password);
        $this->workspaces[]=$copy['work'];
        $this->assertSame($before,$this->databaseSnapshot());
        $package=app(CompanyArchivePackage::class);$manifest=$package->inspect($copy['path'],$this->password);
        $this->assertSame(6,$manifest['version']);$this->assertSame('copy_only',$manifest['purpose']);
        $rows=[];$package->stream($copy['path'],$this->password,$manifest['tables']['sale_payments']['entry'],function($s)use(&$rows){while(($line=fgets($s))!==false)$rows[]=json_decode($line,true);});
        $this->assertSame($payments,$rows);
        Storage::disk('public')->assertExists('item-images/one.png');Storage::disk('public')->assertExists('item-images/two.png');
        $saleIds=[];$package->stream($copy['path'],$this->password,$manifest['tables']['sales']['entry'],function($s)use(&$saleIds){while(($line=fgets($s))!==false)$saleIds[]=CompanyArchiveInventory::decodeRow(json_decode($line,true))['id'];});
        $this->assertSame(['1'],$saleIds); // Foreign-company sale 2 is never pulled into the copy.
        $warnings=array_values(array_filter($manifest['dependency_warnings'],fn($w)=>$w['table']==='sale_payments' && $w['column']==='sale_id'));
        $this->assertCount(1,$warnings);$this->assertSame(2,$warnings[0]['count']);
        $this->rejected(fn()=>$this->service()->validated($this->company(),$copy['path'],$this->password),'copy-only backup');
        $this->assertSame($before,$this->databaseSnapshot());
    }

    public function test_copy_download_requires_own_admin_and_password_and_streams_encrypted_zip(): void
    {
        $this->crossCompanyPaymentFixture();$before=$this->databaseSnapshot();
        $controller=app(\App\Http\Controllers\Company\CompanyArchiveController::class);
        $request=Request::create('/company/one/profile/archive/copy','POST',['current_password'=>'wrong','password'=>$this->password,'password_confirmation'=>$this->password]);
        $request->setUserResolver(fn()=>User::find(1));
        $this->rejected(fn()=>$controller->copy($request,'one',app(\App\Services\CompanyBackupCopyService::class)),'current account password');
        $request->merge(['current_password'=>'account-password']);
        try { $controller->copy($request,'two',app(\App\Services\CompanyBackupCopyService::class));$this->fail('Other company must not be copied.'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {$this->assertSame(403,$e->getStatusCode());}
        $route=app('router')->getRoutes()->match($request);
        $this->assertSame('company.profile.archive.copy',$route->getName());$this->assertContains('auth',$route->gatherMiddleware());
        $response=$controller->copy($request,'one',app(\App\Services\CompanyBackupCopyService::class));
        $this->assertStringContainsString('attachment',$response->headers->get('Content-Disposition'));
        ob_start();try{$response->sendContent();$bytes=ob_get_contents();}finally{ob_end_clean();}
        $this->assertStringStartsWith('PK',$bytes);
        $this->assertSame($before,$this->databaseSnapshot());
        $this->assertSame(0,DB::connection()->transactionLevel());
        $this->assertSame(0,(int)DB::selectOne('PRAGMA query_only')->query_only);
    }

    public function test_copy_lists_missing_required_files_without_changing_records(): void
    {
        $this->requiredDocument('documents/missing-design.pdf');$before=$this->databaseSnapshot();
        $copy=app(\App\Services\CompanyBackupCopyService::class)->create($this->company(),$this->password);$this->workspaces[]=$copy['work'];
        $manifest=app(CompanyArchivePackage::class)->inspect($copy['path'],$this->password);
        $this->assertCount(1,$manifest['missing_required_files']);
        $missing=array_values($manifest['missing_required_files'])[0];
        $this->assertSame('documents/missing-design.pdf',$missing['reference']['path']);
        $this->assertStringContainsString('order_production_files',$missing['source']);
        $this->assertSame($before,$this->databaseSnapshot());
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'attachment is missing');
        $this->assertSame($before,$this->databaseSnapshot());
    }

    public function test_active_http_request_lease_is_released_on_exception(): void
    {
        $middleware = new \App\Http\Middleware\CompanyArchiveAccess;
        $request = Request::create('/company/one/sales');
        $route = new \Illuminate\Routing\Route('GET','company/{slug}/sales',fn()=>null); $route->bind($request);
        $request->setRouteResolver(fn()=>$route); $request->setUserResolver(fn()=>User::find(1));
        try {
            $middleware->handle($request,function(){
                $this->assertSame(1,(int)CompanyArchiveGate::state(1)->active_requests);
                throw new \RuntimeException('test interruption');
            });
        } catch (\RuntimeException $e) { $this->assertSame('test interruption',$e->getMessage()); }
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_tracked_http_exception_and_stream_keep_request_accounting(): void
    {
        (require database_path('migrations/2026_11_21_230000_create_company_archive_request_leases_table.php'))->up();
        $this->test_active_http_request_lease_is_released_on_exception();
        $this->test_streamed_read_only_export_keeps_lease_until_output_and_discards_delayed_updates();
        $this->assertSame(0,DB::table('company_archive_request_leases')->count());
    }

    public function test_tracked_api_archive_round_trip_releases_download_lease(): void
    {
        (require database_path('migrations/2026_11_21_230000_create_company_archive_request_leases_table.php'))->up();
        $this->test_archive_api_complete_encrypted_round_trip_keeps_normal_writes_blocked_and_ignores_company_spoofing();
        $this->assertSame(0,DB::table('company_archive_request_leases')->count());
    }

    public function test_file_cleanup_can_be_retried_after_database_removal(): void
    {
        $path = $this->prepared(); $this->service()->verify($this->company(),$path,$this->password);
        $disk = Storage::disk('public'); $proxy = \Mockery::mock($disk);
        $proxy->shouldReceive('delete')->with('item-images/one.png')->once()->andReturn(false);
        Storage::set('public',$proxy);
        $this->rejected(fn()=>$this->service()->remove($this->company(),$path,$this->password), 'cleanup is incomplete');
        $this->assertSame('cleanup_pending',CompanyArchiveGate::state(1)->status);
        $this->assertSame(0,DB::table('sales')->where('company_id',1)->count());
        Storage::set('public',$disk);
        $this->service()->remove($this->company(),$path,$this->password);
        $this->assertSame('archived',CompanyArchiveGate::state(1)->status);
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_reviewed_shared_legacy_images_are_retained_without_guessing_a_company(): void
    {
        Schema::create('product_background_removes',function(Blueprint $t){$t->id();$t->string('title');});
        DB::table('product_background_removes')->insert(['title'=>'Unattributed legacy image']);
        $path=$this->archived();
        $this->assertSame(1,DB::table('product_background_removes')->count());
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(1,DB::table('product_background_removes')->count());
        $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_archived_account_cannot_be_purged_and_only_its_image_cache_is_evicted(): void
    {
        $key='stock-pdf-v1-'.hash('sha256',json_encode([1,1,'public','item-images/one.png',null,'']));
        \Illuminate\Support\Facades\Cache::store('file')->put($key,'company one cached image',3600);
        \Illuminate\Support\Facades\Cache::store('file')->put('other-company-test-cache','keep',3600);
        $this->archived();
        $this->assertNull(\Illuminate\Support\Facades\Cache::store('file')->get($key));
        $this->assertSame('keep',\Illuminate\Support\Facades\Cache::store('file')->get('other-company-test-cache'));
        $this->rejected(fn()=>app(\App\Services\CompanyPurgeService::class)->purge($this->company()),'Restore or cancel');
        $this->assertSame(2,DB::table('companies')->count());
    }

    public function test_archive_routes_keep_authentication_and_cross_company_admin_is_denied(): void
    {
        $request=Request::create('/company/two/profile/archive/prepare','POST',['current_password'=>'account-password']);
        $route=app('router')->getRoutes()->match($request);
        $this->assertContains('auth',$route->gatherMiddleware());
        $this->assertContains('company.2fa',$route->gatherMiddleware());
        $request->setUserResolver(fn()=>User::find(1));
        try { app(\App\Http\Controllers\Company\CompanyArchiveController::class)->prepare($request,'two',$this->service()); $this->fail('Cross-company archive must be denied.'); }
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }

    public function test_legacy_logical_orphans_round_trip_but_existing_cross_tenant_dependencies_are_rejected(): void
    {
        Schema::create('metal_ledgers',function(Blueprint $t){$t->id();$t->unsignedBigInteger('company_id');$t->unsignedBigInteger('sale_id');});
        DB::table('metal_ledgers')->insert(['id'=>1,'company_id'=>1,'sale_id'=>999999]);
        $path=$this->archived();$this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(999999,(int)DB::table('metal_ledgers')->where('id',1)->value('sale_id'));
        DB::table('metal_ledgers')->where('id',1)->update(['sale_id'=>2]);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'another company');
        $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_queue_scope_ignores_other_company_uploads_but_blocks_this_company_and_unknown_commands(): void
    {
        Schema::create('jobs',function(Blueprint $t){$t->id();$t->text('payload');});
        $payload=function($job){return json_encode(['displayName'=>get_class($job),'data'=>['commandName'=>get_class($job),'command'=>serialize($job)]]);};
        DB::table('jobs')->insert(['payload'=>$payload(new \App\Jobs\ProcessItemSetImageUpload(2,2,'fixture-pending.png','image/png'))]);
        $queue=app(\App\Services\CompanyArchiveQueue::class);
        $this->assertFalse($queue->blocksCompany(1));
        $this->assertTrue($queue->blocksCompany(2));
        $path=$this->prepared();$this->service()->cancel($this->company());
        $this->assertSame(1,DB::table('jobs')->count());
        DB::table('jobs')->insert(['payload'=>'unknown command']);
        $this->assertTrue($queue->blocksCompany(1));
    }

    public function test_retained_account_login_mail_does_not_block_or_get_executed_by_archive_checks(): void
    {
        Schema::create('jobs',function(Blueprint $t){$t->id();$t->text('payload');});
        $mail=new \App\Mail\CompanyLoginMail($this->company(),User::find(1),'fixture-token');
        $job=new \Illuminate\Mail\SendQueuedMailable($mail);
        DB::table('jobs')->insert(['payload'=>json_encode(['displayName'=>get_class($mail),'data'=>['commandName'=>get_class($job),'command'=>serialize($job)]])]);
        \Illuminate\Support\Facades\Mail::fake();
        $this->assertFalse(app(\App\Services\CompanyArchiveQueue::class)->blocksCompany(1));
        \Illuminate\Support\Facades\Mail::assertNothingSent();
        $this->assertSame(1,DB::table('jobs')->count());
    }

    private function cloudFixture(): void
    {
        config(['filesystems.disks.archive_cloud_fixture'=>['driver'=>'s3','bucket'=>'fixture-only','region'=>'us-east-1']]);
        Storage::fake('archive_cloud_fixture');
        Storage::disk('archive_cloud_fixture')->put('company-one.png',"remote original\x00image");
        DB::table('item_sets')->where('id',1)->update(['image_disk'=>'archive_cloud_fixture','image_path'=>'company-one.png']);
        $this->assertSame('s3',config('filesystems.disks.archive_cloud_fixture.driver'));
    }

    public function test_cloud_paths_are_saved_without_contacting_cloud_storage_and_originals_remain(): void
    {
        $this->cloudFixture();$this->app->instance('env','production');
        $cloud=Storage::disk('archive_cloud_fixture'); $local=Storage::disk('local'); $public=Storage::disk('public');
        Storage::shouldReceive('disk')->with('archive_cloud_fixture')->never();
        Storage::shouldReceive('disk')->with('local')->andReturn($local);
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        $path=$this->archived();
        $this->assertSame("remote original\x00image",$cloud->get('company-one.png'));
        $manifest=app(\App\Services\CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertSame(5,$manifest['version']); $this->assertSame('cloud_paths_only',$manifest['media_policy']);
        $this->assertCount(1,$manifest['reference_files']); $this->assertCount(0,$manifest['files']);
        $file=collect($manifest['reference_files'])->firstWhere('path','company-one.png'); $this->assertSame('archive_cloud_fixture',$file['disk']);
        $this->assertArrayNotHasKey('entry',$file); $this->assertArrayNotHasKey('sha256',$file);
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(2,DB::table('sales')->count());
        $this->assertSame('company-one.png',DB::table('item_sets')->where('id',1)->value('image_path'));
        $this->assertSame('archive_cloud_fixture',DB::table('item_sets')->where('id',1)->value('image_disk'));
    }

    private function preparedEmbeddedCloud(): string
    {
        // Recreate an installation-signed v3 ZIP to verify compatibility with existing pendrive files.
        $path=$this->prepared(); $package=app(CompanyArchivePackage::class); $manifest=$package->inspect($path,$this->password);
        $work=$package->workspace(); $this->workspaces[]=$work; $sources=[];
        foreach ($manifest['entries'] as $entry=>$meta) {
            $local=$work.'/'.str_replace('/','-',$entry);
            $package->stream($path,$this->password,$entry,function($stream)use($local){file_put_contents($local,stream_get_contents($stream));}); $sources[$entry]=$local;
        }
        $index=9000;
        foreach ($manifest['reference_files'] as $key=>$file) {
            $entry='files/'.(++$index).'.bin'; $local=$work.'/'.$index.'.bin'; file_put_contents($local,Storage::disk($file['disk'])->get($file['path']));
            $meta=['size'=>filesize($local),'sha256'=>hash_file('sha256',$local)];
            $manifest['files'][$key]=[...$file,'shared'=>false,'entry'=>$entry,...$meta]; $manifest['entries'][$entry]=$meta; $sources[$entry]=$local;
        }
        $manifest['version']=3; unset($manifest['reference_files'],$manifest['media_policy'],$manifest['missing_optional_files']); $legacy=$work.'/legacy-v3.zip';
        $package->create($legacy,$this->password,$manifest,$sources);
        DB::table('company_archive_states')->where('company_id',1)->update(['archive_sha256'=>hash_file('sha256',$legacy)]);
        return $legacy;
    }

    public function test_local_restore_accepts_pre_safety_archive_and_repairs_missing_cloud_image(): void
    {
        $this->cloudFixture();$this->app->instance('env','production');
        $path=$this->preparedEmbeddedCloud(); $this->service()->verify($this->company(),$path,$this->password); $this->service()->remove($this->company(),$path,$this->password);
        Storage::disk('archive_cloud_fixture')->assertExists('company-one.png');
        // Simulate a cloud file removed by an older deployment.
        Storage::disk('archive_cloud_fixture')->delete('company-one.png'); Storage::disk('archive_cloud_fixture')->assertMissing('company-one.png');
        $this->app->instance('env','local');
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame("remote original\x00image",Storage::disk('archive_cloud_fixture')->get('company-one.png'));
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);
    }

    public function test_failed_local_restore_does_not_delete_repaired_cloud_original(): void
    {
        $this->cloudFixture();$this->app->instance('env','production');$path=$this->preparedEmbeddedCloud();
        $this->service()->verify($this->company(),$path,$this->password); $this->service()->remove($this->company(),$path,$this->password);
        Storage::disk('archive_cloud_fixture')->delete('company-one.png');
        $this->app->instance('env','local');
        DB::table('items')->insert(['id'=>1,'company_id'=>2,'item_name'=>'Collision']);
        try{$this->service()->restore($this->company(),$path,$this->password);$this->fail('Expected collision.');}
        catch(\Illuminate\Database\QueryException $e){$this->assertSame('archived',CompanyArchiveGate::state(1)->status);}
        $this->assertSame("remote original\x00image",Storage::disk('archive_cloud_fixture')->get('company-one.png'));
    }

    private function branch(int $id=3, int $parent=1): Company
    {
        DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'parent_company_id'=>$parent,
            'opening_cash'=>100*$id,'opening_balance_date'=>'2026-10-02']);
        DB::table('users')->insert(['id'=>100+$id,'company_id'=>$id,'name'=>'Branch Admin','email'=>'branch'.$id.'@example.test','password'=>Hash::make('account-password')]);
        DB::table('model_has_roles')->insert(['role_id'=>1,'model_type'=>User::class,'model_id'=>100+$id]);
        DB::table('items')->insert(['id'=>$id,'company_id'=>$id,'item_name'=>'Branch ring '.$id]);
        DB::table('item_sets')->insert(['id'=>$id,'company_id'=>$id,'item_id'=>$id,'image_path'=>'item-images/branch-'.$id.'.png']);
        DB::table('customers')->insert(['id'=>$id,'company_id'=>$id,'name'=>'Branch customer '.$id]);
        DB::table('sales')->insert(['id'=>$id,'company_id'=>$id,'customer_id'=>$id,'net_total'=>1000*$id]);
        DB::table('sale_items')->insert(['id'=>$id,'sale_id'=>$id,'itemset_id'=>$id,'fine_weight'=>10*$id]);
        Storage::disk('public')->put('item-images/branch-'.$id.'.png','branch image '.$id);
        return Company::findOrFail($id);
    }

    public function test_main_and_all_branches_round_trip_atomically_with_inactive_branch_and_files(): void
    {
        $this->branch(3);$this->branch(4);DB::table('companies')->where('id',4)->update(['status'=>false]);
        $inventory=app(CompanyArchiveInventory::class);$before=[];
        foreach($inventory->order() as $table)$before[$table]=$inventory->digest($table,[1,3,4]);
        $path=$this->prepared();$manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertSame([1,3,4],array_column($manifest['companies'],'id'));
        $this->assertSame(5,$manifest['version']);
        $this->assertSame(3,$manifest['tables']['sales']['count']);$this->assertCount(3,$manifest['files']);
        foreach([1,3,4] as $id)$this->assertSame('ready',CompanyArchiveGate::state($id)->status);
        $this->assertNull(CompanyArchiveGate::state(3)->archive_path);$this->assertNull(CompanyArchiveGate::state(3)->archive_sha256);
        $this->service()->verify($this->company(),$path,$this->password);
        $this->service()->remove($this->company(),$path,$this->password);
        foreach([1,3,4] as $id){
            $this->assertSame('archived',CompanyArchiveGate::state($id)->status);
            $this->assertFalse(CompanyArchiveGate::enter($id));
            $this->assertSame(0,DB::table('sales')->where('company_id',$id)->count());
            $this->assertEquals(0,DB::table('companies')->where('id',$id)->value('opening_cash'));
        }
        $this->assertSame(1,DB::table('sales')->where('company_id',2)->count());Storage::disk('public')->assertExists('item-images/two.png');
        Storage::disk('public')->assertMissing('item-images/branch-3.png');
        $this->service()->restore($this->company(),$path,$this->password);
        foreach($inventory->order() as $table)$this->assertSame($before[$table],$inventory->digest($table,[1,3,4]));
        foreach([1,3,4] as $id){$this->assertSame('active',CompanyArchiveGate::state($id)->status);$this->assertNull(CompanyArchiveGate::state($id)->summary);}
        $this->assertEquals(300,DB::table('companies')->where('id',3)->value('opening_cash'));
        $this->assertEquals(400,DB::table('companies')->where('id',4)->value('opening_cash'));
        $this->assertFalse((bool)DB::table('companies')->where('id',4)->value('status'));
        $this->assertSame('branch image 3',Storage::disk('public')->get('item-images/branch-3.png'));
    }

    public function test_branch_admin_cannot_download_cancel_or_restore_main_group_archive(): void
    {
        $branch=$this->branch();$path=$this->prepared();
        $request=Request::create('/','POST',['current_password'=>'account-password']);$request->setUserResolver(fn()=>User::find(103));
        $controller=app(\App\Http\Controllers\Company\CompanyArchiveController::class);
        $this->rejected(fn()=>$controller->download($request,$branch->slug),'main company profile');
        $this->rejected(fn()=>$this->service()->cancel($branch),'main company profile');
        $this->rejected(fn()=>$this->service()->restore($branch,$path,$this->password),'main company profile');
        $html=view('company.profile.archive',['company'=>$branch,'user'=>User::find(103)])->with('errors',new \Illuminate\Support\ViewErrorBag)->render();
        $this->assertStringContainsString('main company administrator',$html);$this->assertStringNotContainsString('<form',$html);
        $this->assertSame('ready',CompanyArchiveGate::state(1)->status);$this->assertSame('ready',CompanyArchiveGate::state(3)->status);
    }

    public function test_branch_requests_pause_whole_group_until_drained_then_cancel_reopens_all(): void
    {
        $this->branch();CompanyArchiveGate::enter(3);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'requests are still finishing');
        $this->assertSame('preparing',CompanyArchiveGate::state(1)->status);$this->assertSame('preparing',CompanyArchiveGate::state(3)->status);
        $this->assertFalse(CompanyArchiveGate::enter(1));$this->assertTrue(CompanyArchiveGate::enter(2));CompanyArchiveGate::leave(2);
        CompanyArchiveGate::leave(3);$this->service()->cancel($this->company());
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);$this->assertSame('active',CompanyArchiveGate::state(3)->status);
        $this->assertSame(3,DB::table('sales')->count());
    }

    public function test_branch_own_archive_blocks_main_group_preparation_without_partial_freeze(): void
    {
        $branch=$this->branch();$this->service()->prepare($branch,103,$this->password);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'already has an archive');
        $this->assertSame('active',CompanyArchiveGate::state(1)->status??'active');$this->assertSame('ready',CompanyArchiveGate::state(3)->status);
        $this->service()->cancel($branch);$this->assertSame(3,DB::table('sales')->count());
    }

    public function test_existing_single_company_archive_never_claims_new_branches(): void
    {
        $path=$this->prepared();$this->branch();
        $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);$this->assertArrayNotHasKey('companies',$manifest);$this->assertSame(5,$manifest['version']);
        $this->service()->verify($this->company(),$path,$this->password);$this->service()->remove($this->company(),$path,$this->password);
        $this->assertSame(1,DB::table('sales')->where('company_id',3)->count());$this->assertNull(CompanyArchiveGate::state(3));
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame(3,DB::table('sales')->count());$this->assertEquals(300,DB::table('companies')->where('id',3)->value('opening_cash'));
    }

    public function test_group_hierarchy_changes_and_a_branch_queue_job_block_without_deleting_records(): void
    {
        $this->branch();$path=$this->prepared();$this->branch(4);
        $this->rejected(fn()=>$this->service()->verify($this->company(),$path,$this->password),'branches or their ownership changed');
        $this->service()->cancel($this->company());$this->assertSame('active',CompanyArchiveGate::state(3)->status);
        Schema::create('jobs',function(Blueprint $t){$t->id();$t->text('payload');});
        $job=new \App\Jobs\ProcessItemSetImageUpload(3,3,'fixture.png','image/png');
        DB::table('jobs')->insert(['payload'=>json_encode(['displayName'=>get_class($job),'data'=>['commandName'=>get_class($job),'command'=>serialize($job)]])]);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'jobs must finish');
        $this->assertSame(4,DB::table('sales')->count());
    }

    public function test_group_restore_collision_rolls_back_every_company_and_shared_outside_image_is_kept(): void
    {
        $this->branch();DB::table('item_sets')->where('id',3)->update(['image_path'=>'item-images/two.png']);
        $path=$this->archived();Storage::disk('public')->assertExists('item-images/two.png');
        DB::table('items')->insert(['id'=>3,'company_id'=>2,'item_name'=>'Other company collision']);
        try{$this->service()->restore($this->company(),$path,$this->password);$this->fail('Expected collision.');}
        catch(\Illuminate\Database\QueryException $e){$this->assertSame('archived',CompanyArchiveGate::state(1)->status);}
        $this->assertSame('archived',CompanyArchiveGate::state(3)->status);
        $this->assertSame(0,DB::table('sales')->whereIn('company_id',[1,3])->count());$this->assertSame(1,DB::table('sales')->where('company_id',2)->count());
        Storage::disk('public')->assertExists('item-images/two.png');
    }

    private function readOnlyRequest(string $method='GET',string $action='index'): Request
    {
        $request=Request::create('/company/one/items',$method,[],[],[],['HTTP_ACCEPT'=>'application/json']);
        $route=new \Illuminate\Routing\Route($method,'company/{slug}/items',['uses'=>'ArchiveReadTestController@'.$action,'as'=>'company.items.'.$action]);
        $route->bind($request);$request->setRouteResolver(fn()=>$route);$request->setUserResolver(fn()=>User::find(1));
        $this->app->instance('request',$request);
        return $request;
    }

    public function test_ready_lists_render_and_auto_updates_are_rolled_back_including_nested_transactions(): void
    {
        $this->prepared();$request=$this->readOnlyRequest();
        $before=DB::table('sales')->where('id',1)->value('net_total');
        $response=(new \App\Http\Middleware\CompanyArchiveAccess)->handle($request,function(){
            $this->assertSame(1,(int)CompanyArchiveGate::state(1)->active_requests);
            DB::table('items')->insert(['id'=>99,'company_id'=>1,'item_name'=>'Must not persist']);
            DB::table('sales')->where('id',1)->update(['net_total'=>0]);
            DB::transaction(fn()=>DB::table('customers')->insert(['id'=>99,'company_id'=>1,'name'=>'Temporary default']));
            return response()->json([['id'=>1,'item_name'=>'Ring']]);
        });
        $this->assertSame([['id'=>1,'item_name'=>'Ring']],$response->getData(true));
        $this->assertSame('1',$response->headers->get('X-Company-Archive-Read-Only'));
        $this->assertFalse(DB::table('items')->where('id',99)->exists());$this->assertFalse(DB::table('customers')->where('id',99)->exists());
        $this->assertSame($before,DB::table('sales')->where('id',1)->value('net_total'));
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_archived_empty_lists_do_not_recreate_default_categories(): void
    {
        Schema::create('category_people',function(Blueprint $t){$t->id();$t->unsignedBigInteger('company_id');$t->string('category_name');$t->boolean('is_system_default')->default(false);$t->timestamps();});
        $this->archived();$request=$this->readOnlyRequest();
        $response=(new \App\Http\Middleware\CompanyArchiveAccess)->handle($request,function(){
            \App\Models\CategoryPerson::ensureCompanyDefaults(1);
            $this->assertSame(0,DB::table('category_people')->where('company_id',1)->count());
            return response()->json(DB::table('items')->where('company_id',1)->get()->all());
        });
        $this->assertSame(200,$response->getStatusCode());$this->assertSame([],$response->getData(true));
        $this->assertSame(0,DB::table('category_people')->where('company_id',1)->count());
    }

    public function test_archived_write_methods_and_legacy_mutating_get_actions_are_never_executed(): void
    {
        $this->archived();$middleware=new \App\Http\Middleware\CompanyArchiveAccess;
        foreach(['POST','PUT','PATCH','DELETE'] as $method){
            $called=false;$request=$this->readOnlyRequest($method,'store');
            $response=$middleware->handle($request,function()use(&$called){$called=true;return response('wrong');});
            $this->assertSame(423,$response->getStatusCode(),$method);$this->assertFalse($called);
        }
        foreach(['create','edit','finalizeGet','printPdf','printDirect','printLabels','process','destroy'] as $action){
            $called=false;$request=$this->readOnlyRequest('GET',$action);
            $response=$middleware->handle($request,function()use(&$called){$called=true;return response('wrong');});
            $this->assertSame(423,$response->getStatusCode(),$action);$this->assertFalse($called);
        }
        $this->assertSame(0,DB::table('items')->where('company_id',1)->count());
    }

    public function test_busy_archive_operations_still_block_business_navigation(): void
    {
        CompanyArchiveGate::ensure(1);$middleware=new \App\Http\Middleware\CompanyArchiveAccess;
        foreach(['building','verifying','removing','restoring','cancelling'] as $status){
            DB::table('company_archive_states')->where('company_id',1)->update(['status'=>$status]);
            $request=$this->readOnlyRequest();$called=false;
            $response=$middleware->handle($request,function()use(&$called){$called=true;return response('wrong');});
            $this->assertSame(423,$response->getStatusCode());$this->assertFalse($called);
        }
    }

    public function test_streamed_read_only_export_keeps_lease_until_output_and_discards_delayed_updates(): void
    {
        $this->prepared();$request=$this->readOnlyRequest('GET','exportExcel');
        $response=(new \App\Http\Middleware\CompanyArchiveAccess)->handle($request,fn()=>response()->stream(function(){
            DB::table('items')->insert(['id'=>99,'company_id'=>1,'item_name'=>'Stream must not persist']);echo 'fixture CSV';
        }));
        $this->assertSame(1,(int)CompanyArchiveGate::state(1)->active_requests);
        ob_start();$response->sendContent();$output=ob_get_clean();
        $this->assertSame('fixture CSV',$output);$this->assertFalse(DB::table('items')->where('id',99)->exists());
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_read_only_exception_rolls_back_and_releases_request_lease(): void
    {
        $this->prepared();$request=$this->readOnlyRequest();
        try{(new \App\Http\Middleware\CompanyArchiveAccess)->handle($request,function(){
            DB::table('items')->insert(['id'=>99,'company_id'=>1,'item_name'=>'Must roll back']);throw new \RuntimeException('read failed');
        });$this->fail('Expected failure.');}catch(\RuntimeException $e){$this->assertSame('read failed',$e->getMessage());}
        $this->assertFalse(DB::table('items')->where('id',99)->exists());$this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_real_item_screen_opens_in_archive_mode_and_forged_save_is_blocked(): void
    {
        Schema::create('item_labels',function(Blueprint $t){$t->id();$t->unsignedBigInteger('item_id');});
        $path=$this->prepared();$this->actingAs(User::find(1));
        \Illuminate\Support\Facades\Route::middleware('web')->get('/company/{slug}/archive-read-items',[\App\Http\Controllers\Company\ItemController::class,'index']);
        \Illuminate\Support\Facades\Route::middleware('web')->get('/company/{slug}/archive-read-item-data',[\App\Http\Controllers\Company\ItemController::class,'data']);
        $response=$this->get('/company/one/archive-read-items');
        $response->assertOk()->assertSee('Item List')->assertDontSee('<strong>Archive mode',false)->assertSee('company-archive-readonly.js',false)->assertSee('aria-disabled="true"',false);
        \Illuminate\Support\Facades\Route::middleware('web')->post('/company/{slug}/archive-write-item',function(){DB::table('items')->insert(['id'=>99,'company_id'=>1,'item_name'=>'Forbidden']);return response('wrong');});
        $this->postJson('/company/one/archive-write-item')->assertStatus(423);
        $this->assertFalse(DB::table('items')->where('id',99)->exists());
        $this->assertSame(2,DB::table('items')->count());
        $this->getJson('/company/one/archive-read-item-data?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal',1);
        $this->service()->verify($this->company(),$path,$this->password);$this->service()->remove($this->company(),$path,$this->password);
        $this->get('/company/one/archive-read-items')->assertOk()->assertSee('Item List')->assertSee('company-archive-readonly.js',false);
        $this->getJson('/company/one/archive-read-item-data?draw=1&start=0&length=10')->assertOk()->assertJsonPath('recordsTotal',0);
        $this->service()->restore($this->company(),$path,$this->password);
        $this->get('/company/one/archive-read-items')->assertOk()->assertDontSee('Archive mode')->assertDontSee('company-archive-readonly.js',false);
        $this->postJson('/company/one/archive-write-item')->assertOk();$this->assertTrue(DB::table('items')->where('id',99)->exists());
    }

    private function staffFixture(): void
    {
        Schema::table('users', function (Blueprint $t) {
            $t->string('profile_image')->nullable(); $t->string('role')->nullable();
            $t->string('remember_token')->nullable(); $t->string('active_web_session_id')->nullable();
            $t->unsignedBigInteger('active_api_token_id')->nullable(); $t->string('password_set_url')->nullable();
        });
        Schema::create('permissions', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('guard_name'); });
        Schema::create('model_has_permissions', function (Blueprint $t) {
            $t->foreignId('permission_id')->constrained(); $t->string('model_type'); $t->unsignedBigInteger('model_id');
            $t->primary(['permission_id','model_type','model_id']);
        });
        Schema::create('role_has_permissions', function (Blueprint $t) {
            $t->foreignId('role_id')->constrained(); $t->foreignId('permission_id')->constrained(); $t->primary(['role_id','permission_id']);
        });
        Schema::create('worker_allowed_devices', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained(); $t->foreignId('user_id')->constrained()->cascadeOnDelete();
            $t->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('device_id');
        });
        Schema::create('company_app_themes', function (Blueprint $t) {
            $t->id(); $t->foreignId('company_id')->constrained(); $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete(); $t->string('theme');
        });
        Schema::table('sales', fn (Blueprint $t) => $t->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete());
        Schema::create('sessions', function (Blueprint $t) { $t->string('id')->primary(); $t->unsignedBigInteger('user_id'); });
        Schema::create('personal_access_tokens', function (Blueprint $t) { $t->id(); $t->string('tokenable_type'); $t->unsignedBigInteger('tokenable_id'); });
        Schema::create('password_set_tokens', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('user_id'); });
        Schema::create('password_reset_tokens', function (Blueprint $t) { $t->string('email')->primary(); $t->string('token'); });
        DB::table('roles')->insert(['id'=>2,'name'=>'sales_staff','guard_name'=>'web','company_id'=>null]);
        DB::table('permissions')->insert([['id'=>1,'name'=>'sale-view','guard_name'=>'web'],['id'=>2,'name'=>'item-view','guard_name'=>'web']]);
        DB::table('model_has_roles')->insert(['role_id'=>2,'model_type'=>User::class,'model_id'=>3]);
        DB::table('model_has_permissions')->insert(['permission_id'=>2,'model_type'=>User::class,'model_id'=>3]);
        DB::table('role_has_permissions')->insert(['role_id'=>2,'permission_id'=>1]);
        DB::table('users')->where('id',3)->update(['profile_image'=>'uploads/staff.png','active_web_session_id'=>'old-staff-session','active_api_token_id'=>3,'remember_token'=>'old-remember','password_set_url'=>'old-reset-link']);
        DB::table('users')->where('id',1)->update(['profile_image'=>'uploads/admin.png']);
        DB::table('sales')->where('id',1)->update(['created_by'=>3]);
        DB::table('worker_allowed_devices')->insert([['id'=>1,'company_id'=>1,'user_id'=>3,'approved_by'=>1,'device_id'=>'staff-device'],['id'=>2,'company_id'=>1,'user_id'=>1,'approved_by'=>3,'device_id'=>'admin-device']]);
        DB::table('company_app_themes')->insert(['id'=>1,'company_id'=>1,'created_by'=>3,'theme'=>'dark']);
        DB::table('sessions')->insert([['id'=>'staff-session','user_id'=>3],['id'=>'admin-session','user_id'=>1]]);
        DB::table('personal_access_tokens')->insert([['id'=>3,'tokenable_type'=>User::class,'tokenable_id'=>3],['id'=>1,'tokenable_type'=>User::class,'tokenable_id'=>1]]);
        DB::table('password_set_tokens')->insert(['user_id'=>3]);
        DB::table('password_reset_tokens')->insert(['email'=>'staff@example.test','token'=>'expired-restore-must-not-reactivate']);
        Storage::disk('public')->put('uploads/staff.png','staff profile bytes'); Storage::disk('public')->put('uploads/admin.png','admin profile bytes');
    }

    public function test_staff_accounts_roles_permissions_devices_and_images_move_and_restore_without_admin_or_sessions(): void
    {
        $this->staffFixture(); $this->branch();
        DB::table('users')->insert(['id'=>104,'company_id'=>3,'name'=>'Branch Staff','email'=>'branch-staff@example.test','password'=>Hash::make('branch-password')]);
        DB::table('model_has_roles')->insert(['role_id'=>2,'model_type'=>User::class,'model_id'=>104]);
        $admin=(array)DB::table('users')->where('id',1)->first(); $other=(array)DB::table('users')->where('id',2)->first();
        $staffPassword=DB::table('users')->where('id',3)->value('password');
        $path=$this->prepared(); $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertSame([3,104],$manifest['staff']['ids']); $this->assertSame('staff_move',$manifest['tables']['users']['mode']);
        $this->assertSame(2,$manifest['tables']['users']['count']); $this->assertSame(2,$manifest['tables']['model_has_roles']['count']);
        $this->assertFalse(collect($manifest['files'])->contains(fn($f)=>$f['path']==='uploads/admin.png'));
        $this->service()->verify($this->company(),$path,$this->password); $this->service()->remove($this->company(),$path,$this->password);
        $this->assertSame([1,2,103],DB::table('users')->orderBy('id')->pluck('id')->all());
        $this->assertSame($admin,(array)DB::table('users')->where('id',1)->first()); $this->assertSame($other,(array)DB::table('users')->where('id',2)->first());
        $this->assertSame(0,DB::table('model_has_roles')->whereIn('model_id',[3,104])->count());
        $this->assertSame(0,DB::table('model_has_permissions')->where('model_id',3)->count());
        $this->assertSame(1,DB::table('worker_allowed_devices')->count()); $this->assertNull(DB::table('worker_allowed_devices')->where('id',2)->value('approved_by'));
        $this->assertNull(DB::table('company_app_themes')->value('created_by')); $this->assertSame('dark',DB::table('company_app_themes')->value('theme'));
        $this->assertSame(1,DB::table('sessions')->count()); $this->assertSame(1,DB::table('personal_access_tokens')->count());
        $this->assertSame(0,DB::table('password_set_tokens')->count()); $this->assertSame(0,DB::table('password_reset_tokens')->count());
        Storage::disk('public')->assertMissing('uploads/staff.png'); Storage::disk('public')->assertExists('uploads/admin.png');
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame([1,2,3,103,104],DB::table('users')->orderBy('id')->pluck('id')->all());
        $this->assertSame($staffPassword,DB::table('users')->where('id',3)->value('password'));
        $this->assertSame('archive-relogin-required',DB::table('users')->where('id',3)->value('active_web_session_id'));
        $this->assertNull(DB::table('users')->where('id',3)->value('remember_token')); $this->assertNull(DB::table('users')->where('id',3)->value('active_api_token_id'));
        $this->assertSame(2,DB::table('model_has_roles')->where('model_id',3)->value('role_id')); $this->assertSame(2,DB::table('model_has_permissions')->where('model_id',3)->value('permission_id'));
        $this->assertSame(3,DB::table('sales')->where('id',1)->value('created_by')); $this->assertSame(3,DB::table('company_app_themes')->value('created_by'));
        $this->assertSame(3,DB::table('worker_allowed_devices')->where('id',2)->value('approved_by')); $this->assertSame(2,DB::table('worker_allowed_devices')->count());
        $this->assertSame('staff profile bytes',Storage::disk('public')->get('uploads/staff.png'));
        $this->assertSame($admin,(array)DB::table('users')->where('id',1)->first()); $this->assertSame(1,DB::table('personal_access_tokens')->count());
    }

    public function test_staff_changes_after_export_and_shared_role_changes_stop_remove_or_restore(): void
    {
        $this->staffFixture(); $path=$this->prepared();
        DB::table('users')->where('id',3)->update(['name'=>'Changed Staff']);
        $this->rejected(fn()=>$this->service()->verify($this->company(),$path,$this->password),'Staff data changed');
        $this->service()->cancel($this->company()); $path=$this->archived();
        DB::table('role_has_permissions')->insert(['role_id'=>2,'permission_id'=>2]);
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password),'permissions changed');
        $this->assertFalse(DB::table('users')->where('id',3)->exists());
        DB::table('role_has_permissions')->where('role_id',2)->where('permission_id',2)->delete();
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame('Changed Staff',DB::table('users')->where('id',3)->value('name'));
    }

    public function test_staff_cross_company_references_stop_archive_without_removing_any_records(): void
    {
        $this->staffFixture(); DB::table('sales')->where('id',2)->update(['created_by'=>3]);
        $this->rejected(fn()=>$this->prepared(),'Another company references');
        $this->assertSame(3,DB::table('users')->count()); $this->assertSame(2,DB::table('sales')->count());
        $this->service()->cancel($this->company());
        DB::table('sales')->where('id',2)->update(['created_by'=>null]);
        DB::table('worker_allowed_devices')->where('id',1)->update(['company_id'=>2]);
        $this->rejected(fn()=>$this->prepared(),'Another company has device records');
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);
    }

    public function test_staff_id_collision_and_retained_link_changes_do_not_overwrite_or_partially_restore(): void
    {
        $this->staffFixture(); $path=$this->archived();
        DB::table('users')->insert(['id'=>3,'company_id'=>2,'name'=>'Other user','email'=>'other-staff@example.test','password'=>'unchanged']);
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password),'IDs or assignments');
        $this->assertSame('Other user',DB::table('users')->where('id',3)->value('name')); DB::table('users')->where('id',3)->delete();
        DB::table('company_app_themes')->update(['created_by'=>1]);
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password),'reference changed');
        $this->assertFalse(DB::table('users')->where('id',3)->exists()); $this->assertSame(0,DB::table('sales')->where('company_id',1)->count());
        $this->assertSame(1,DB::table('company_app_themes')->value('created_by')); Storage::disk('public')->assertMissing('uploads/staff.png');
    }

    public function test_all_company_admins_including_legacy_role_stay_out_of_staff_archive(): void
    {
        Schema::table('users',fn(Blueprint $t)=>$t->string('role')->nullable());
        DB::table('users')->where('id',3)->update(['role'=>'company_admin']);
        $path=$this->archived(); $this->assertSame(3,DB::table('users')->count());
        $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password); $this->assertSame([],$manifest['staff']['ids']);
        $this->assertSame(0,$manifest['tables']['users']['count']); $this->service()->restore($this->company(),$path,$this->password);
    }

    public function test_legacy_v1_and_v2_archives_restore_with_old_retained_user_policy(): void
    {
        foreach ([1,2] as $version) {
            if ($version===2) $this->branch();
            $path=$this->prepared(); $package=app(CompanyArchivePackage::class); $manifest=$package->inspect($path,$this->password);
            $work=$package->workspace(); $this->workspaces[]=$work; $sources=[];
            foreach ($manifest['entries'] as $entry=>$meta) {
                $local=$work.'/'.str_replace('/','-',$entry);
                $package->stream($path,$this->password,$entry,function($stream)use($local){file_put_contents($local,stream_get_contents($stream));}); $sources[$entry]=$local;
            }
            $ids=$version===2?[1,3]:[1];
            foreach (\App\Services\CompanyArchiveUsers::TABLES as $table) if (isset($manifest['tables'][$table])) {
                $entry=$manifest['tables'][$table]['entry']; $lines=[];
                foreach (app(CompanyArchiveInventory::class)->ordered($table,$ids)->get() as $row) $lines[]=json_encode(CompanyArchiveInventory::encodeRow($row),JSON_THROW_ON_ERROR)."\n";
                file_put_contents($sources[$entry],implode('',$lines)); $meta=['size'=>filesize($sources[$entry]),'sha256'=>hash_file('sha256',$sources[$entry])];
                $manifest['entries'][$entry]=$meta; $manifest['tables'][$table]=['entry'=>$entry,'count'=>count($lines),'mode'=>'retained',...$meta];
            }
            $manifest['version']=$version; unset($manifest['staff'],$manifest['reference_files'],$manifest['media_policy'],$manifest['missing_optional_files']); $legacy=$work.'/legacy.zip'; $package->create($legacy,$this->password,$manifest,$sources);
            DB::table('company_archive_states')->where('company_id',1)->update(['archive_sha256'=>hash_file('sha256',$legacy)]);
            $this->service()->verify($this->company(),$legacy,$this->password); $this->service()->remove($this->company(),$legacy,$this->password);
            $this->assertTrue(DB::table('users')->where('id',3)->exists());
            $this->service()->restore($this->company(),$legacy,$this->password); $this->assertSame('active',CompanyArchiveGate::state(1)->status);
        }
    }

    private function apiAdmin(int $id = 1): void
    {
        if (!Schema::hasColumn('users','is_active')) Schema::table('users',fn(Blueprint $t)=>$t->boolean('is_active')->default(true));
        \Laravel\Sanctum\Sanctum::actingAs(User::findOrFail($id));
    }

    private function apiUpload(string $path): \Illuminate\Http\UploadedFile
    {
        return \Illuminate\Http\UploadedFile::fake()->createWithContent('saved-company.zip',file_get_contents($path));
    }

    public function test_archive_api_requires_auth_admin_and_current_password_and_does_not_expose_private_paths(): void
    {
        $this->getJson('/api/company/archive/status')->assertUnauthorized();
        foreach (['prepare','download','verify','remove','restore','cancel'] as $action) $this->postJson('/api/company/archive/'.$action)->assertUnauthorized();
        $this->apiAdmin(3);
        $this->getJson('/api/company/archive/status')->assertForbidden();
        $this->postJson('/api/company/archive/cancel',['current_password'=>'account-password'])->assertForbidden();
        $this->apiAdmin();
        $data=$this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.status','active')->json('data');
        $this->assertArrayNotHasKey('archive_path',$data); $this->assertArrayNotHasKey('archive_sha256',$data);
        foreach (['prepare','download','verify','remove','restore','cancel'] as $action) {
            $this->postJson('/api/company/archive/'.$action,['current_password'=>'wrong-password'])->assertUnprocessable()->assertJsonPath('code','VALIDATION_ERROR');
        }
        $this->assertSame(2,DB::table('sales')->count());
        $this->assertSame('active',CompanyArchiveGate::state(1)->status??'active');
    }

    public function test_archive_api_complete_encrypted_round_trip_keeps_normal_writes_blocked_and_ignores_company_spoofing(): void
    {
        $this->apiAdmin(); $fields=['current_password'=>'account-password','password'=>$this->password];
        $this->postJson('/api/company/archive/prepare',$fields+['password_confirmation'=>$this->password,'company_id'=>2])->assertForbidden();
        $this->postJson('/api/company/archive/prepare',$fields+['password_confirmation'=>$this->password])
            ->assertOk()->assertJsonPath('data.status','ready')->assertJsonPath('data.company.id',1);
        $this->assertNull(CompanyArchiveGate::state(2));
        $status=$this->getJson('/api/company/archive/status')->assertOk()->json('data');
        $this->assertStringContainsString(CompanyArchiveGate::state(1)->archive_id,$status['filename']);
        $download=$this->postJson('/api/company/archive/download',['current_password'=>'account-password'])->assertOk()->assertHeader('X-Content-Type-Options','nosniff');
        $this->assertSame(1,(int)CompanyArchiveGate::state(1)->active_requests);
        $bytes=$download->streamedContent();
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
        $work=app(CompanyArchivePackage::class)->workspace(); $this->workspaces[]=$work; $path=$work.'/api-saved.zip'; file_put_contents($path,$bytes);
        $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password); $this->assertSame(5,$manifest['version']);
        $this->post('/api/company/archive/verify',$fields+['archive_file'=>$this->apiUpload($path)],['Accept'=>'application/json'])
            ->assertOk()->assertJsonPath('data.status','verified');
        $this->postJson('/api/create_users',[])->assertStatus(423)->assertJsonPath('code','COMPANY_ARCHIVED');
        $this->post('/api/company/archive/remove',$fields+['archive_file'=>$this->apiUpload($path),'company_confirmation'=>'One','second_copy'=>'1'],['Accept'=>'application/json'])
            ->assertOk()->assertJsonPath('data.status','archived')->assertJsonPath('data.available_actions',['restore']);
        $this->assertFalse(DB::table('users')->where('id',3)->exists()); $this->assertTrue(DB::table('users')->where('id',1)->exists());
        $this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.status','archived');
        $this->post('/api/company/archive/restore',$fields+['archive_file'=>$this->apiUpload($path)],['Accept'=>'application/json'])
            ->assertOk()->assertJsonPath('data.status','active')->assertJsonPath('data.filename',null);
        $this->assertTrue(DB::table('users')->where('id',3)->exists()); $this->assertSame(2,DB::table('sales')->count());
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_archive_api_upload_confirmations_cancellation_and_feature_disabled_recovery(): void
    {
        $this->apiAdmin(); $path=$this->prepared(); $fields=['current_password'=>'account-password','password'=>$this->password];
        $this->post('/api/company/archive/verify',$fields,['Accept'=>'application/json'])->assertUnprocessable()->assertJsonValidationErrors('archive_file');
        $this->service()->verify($this->company(),$path,$this->password);
        $this->post('/api/company/archive/remove',$fields+['archive_file'=>$this->apiUpload($path),'company_confirmation'=>'One'],['Accept'=>'application/json'])
            ->assertUnprocessable()->assertJsonValidationErrors('second_copy');
        $this->post('/api/company/archive/remove',$fields+['archive_file'=>$this->apiUpload($path),'company_confirmation'=>'Wrong company','second_copy'=>1],['Accept'=>'application/json'])->assertUnprocessable();
        $this->assertSame(2,DB::table('sales')->count());
        config(['company_archive.enabled'=>false]);
        $this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.enabled',false)->assertJsonPath('data.status','verified');
        $this->postJson('/api/company/archive/cancel',['current_password'=>'account-password'])->assertOk()->assertJsonPath('data.status','active')->assertJsonPath('data.available_actions',[]);
        $this->assertSame(2,DB::table('sales')->count());
        $this->postJson('/api/company/archive/prepare',$fields+['password_confirmation'=>$this->password])->assertUnprocessable();
    }

    public function test_archive_api_parent_group_cannot_be_managed_or_downloaded_by_a_branch_admin(): void
    {
        $this->apiAdmin(); $this->branch(); $this->prepared(); $this->apiAdmin(103);
        $this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.managed_by_parent',true)
            ->assertJsonPath('data.available_actions',[])->assertJsonPath('data.filename',null)->assertJsonPath('data.archive_id',null)->assertJsonPath('data.summary',null);
        foreach (['download','cancel','restore'] as $action) $this->postJson('/api/company/archive/'.$action,['current_password'=>'account-password'])->assertUnprocessable();
        $this->assertSame('ready',CompanyArchiveGate::state(1)->status); $this->assertSame('ready',CompanyArchiveGate::state(3)->status);
    }

    public function test_archive_api_real_bearer_token_keeps_admin_restore_access_and_revokes_archived_staff(): void
    {
        Schema::table('users',fn(Blueprint $t)=>$t->boolean('is_active')->default(true));
        (require database_path('migrations/2026_02_05_115554_create_personal_access_tokens_table.php'))->up();
        $adminToken=User::findOrFail(1)->createToken('test-admin-archive')->plainTextToken;
        $staffToken=User::findOrFail(3)->createToken('test-staff-archive')->plainTextToken;
        $path=$this->archived();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/company/archive/status',['Authorization'=>'Bearer '.$adminToken])->assertOk()->assertJsonPath('data.status','archived');
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/company/archive/status',['Authorization'=>'Bearer '.$staffToken])->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->post('/api/company/archive/restore',['current_password'=>'account-password','password'=>$this->password,'archive_file'=>$this->apiUpload($path)],
            ['Accept'=>'application/json','Authorization'=>'Bearer '.$adminToken])->assertOk()->assertJsonPath('data.status','active');
        $this->assertSame(1,DB::table('personal_access_tokens')->count());
        $this->assertTrue(DB::table('users')->where('id',3)->exists());
    }

    public function test_archive_api_missing_download_file_releases_lease_and_busy_status_remains_readable(): void
    {
        $this->apiAdmin(); $this->prepared(); $state=CompanyArchiveGate::state(1);
        Storage::disk('local')->delete($state->archive_path);
        $this->postJson('/api/company/archive/download',['current_password'=>'account-password'])->assertUnprocessable();
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
        DB::table('company_archive_states')->where('company_id',1)->update(['status'=>'restoring']);
        $this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.status','restoring')->assertJsonPath('data.available_actions',[]);
        $this->postJson('/api/company/archive/cancel',['current_password'=>'account-password'])->assertUnprocessable();
        $this->assertSame('restoring',CompanyArchiveGate::state(1)->status);
    }

    public function test_api_auth_routes_are_reachable_during_archive_without_sending_mail(): void
    {
        $this->apiAdmin(); $this->archived();
        $auth=$this->mock(\App\Http\Controllers\Api\AuthController::class);
        foreach (['login','verifyOtp','logout'] as $method) $auth->shouldReceive($method)->once()->andReturn(response()->json(['success'=>true,'auth_route_reached'=>true]));
        foreach (['login','verify-otp','logout'] as $route) $this->postJson('/api/company/'.$route,['company_id'=>1])->assertOk()->assertJsonPath('auth_route_reached',true);
        $this->assertSame('archived',CompanyArchiveGate::state(1)->status); $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
    }

    public function test_temporary_workspace_exception_does_not_freeze_company_or_branches(): void
    {
        $this->branch();
        $package=\Mockery::mock(CompanyArchivePackage::class)->makePartial();
        $package->shouldReceive('workspace')->once()->andThrow(new \RuntimeException('temporary-folder-failure'));
        $service=new CompanyArchiveService(app(CompanyArchiveInventory::class),$package);
        try { $service->prepare($this->company(),1,$this->password); $this->fail('Expected workspace failure.'); }
        catch (\RuntimeException $e) { $this->assertSame('temporary-folder-failure',$e->getMessage()); }
        foreach ([1,3] as $id) $this->assertSame('active',CompanyArchiveGate::state($id)->status??'active');
        $this->assertSame(3,DB::table('sales')->count()); $this->assertSame(4,DB::table('users')->count());
    }

    public function test_failed_temporary_directory_creation_is_reported_before_freezing(): void
    {
        \Illuminate\Support\Facades\File::shouldReceive('makeDirectory')->once()->andReturn(false);
        $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'temporary storage is not writable');
        $this->assertNull(CompanyArchiveGate::state(1)); $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_failure_after_build_starts_leaves_group_retryable_and_does_not_change_another_build(): void
    {
        $this->branch();
        $inventory=\Mockery::mock(CompanyArchiveInventory::class)->makePartial();
        $inventory->shouldReceive('order')->once()->andThrow(new \RuntimeException('test-build-failure'));
        $service=new CompanyArchiveService($inventory,app(CompanyArchivePackage::class));
        try { $service->prepare($this->company(),1,$this->password); $this->fail('Expected build failure.'); }
        catch (\RuntimeException $e) { $this->assertSame('test-build-failure',$e->getMessage()); }
        foreach ([1,3] as $id) $this->assertSame('preparing',CompanyArchiveGate::state($id)->status);
        $this->service()->cancel($this->company());
        foreach ([1,3] as $id) $this->assertSame('active',CompanyArchiveGate::state($id)->status);
        $this->prepared(); $this->rejected(fn()=>$this->service()->prepare($this->company(),1,$this->password),'already has an archive');
        foreach ([1,3] as $id) $this->assertSame('ready',CompanyArchiveGate::state($id)->status);
        $this->assertSame(3,DB::table('sales')->count());
    }

    public function test_build_failure_records_safe_progress_without_logging_passwords_or_raw_exception(): void
    {
        $package=\Mockery::mock(CompanyArchivePackage::class)->makePartial();
        $package->shouldReceive('create')->once()->andThrow(new \RuntimeException('secret-archive-password private-server-path'));
        $service=new CompanyArchiveService(app(CompanyArchiveInventory::class),$package);
        try { $service->prepare($this->company(),1,$this->password); $this->fail('Expected ZIP failure.'); }
        catch (\RuntimeException $e) { $this->assertStringContainsString('private-server-path',$e->getMessage()); }
        $state=CompanyArchiveGate::state(1); $summary=json_decode($state->summary,true);
        $this->assertSame('preparing',$state->status); $this->assertSame('encrypting_zip',$summary['progress']['stage']);
        $this->assertSame('BUILD_FAILED',$summary['progress']['error_code']); $this->assertNotEmpty($summary['progress']['updated_at']);
        $this->assertStringNotContainsString($this->password,$state->summary); $this->assertStringNotContainsString('private-server-path',$state->summary);
        $html=view('company.profile.archive',['company'=>$this->company(),'user'=>User::find(1)])->with('errors',new \Illuminate\Support\ViewErrorBag)->render();
        $this->assertStringContainsString('Encrypting ZIP',$html); $this->assertStringContainsString('BUILD_FAILED',$html);
        $this->assertStringNotContainsString('0 records in the archive',$html);
        $this->apiAdmin();
        $this->getJson('/api/company/archive/status')->assertOk()->assertJsonPath('data.summary.progress.stage','encrypting_zip');
        $this->assertSame(2,DB::table('sales')->count()); $this->assertSame(3,DB::table('users')->count());
        $this->service()->cancel($this->company()); $this->assertNull(CompanyArchiveGate::state(1)->summary);
    }

    public function test_missing_zip_support_fails_before_any_workspace_is_paused(): void
    {
        $package=\Mockery::mock(CompanyArchivePackage::class)->makePartial();
        $package->shouldReceive('assertAvailable')->once()->andReturnUsing(fn()=>CompanyArchiveGate::check(false,'PHP ZIP with AES-256 encryption is required.'));
        $service=new CompanyArchiveService(app(CompanyArchiveInventory::class),$package);
        $this->rejected(fn()=>$service->prepare($this->company(),1,$this->password),'PHP ZIP');
        $this->assertNull(CompanyArchiveGate::state(1)); $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_archive_dedicated_log_records_workflow_timing_without_credentials_or_business_rows(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/archive-log-'.bin2hex(random_bytes(6)));
        DB::table('items')->where('id',1)->update(['item_name'=>'PRIVATE-PRODUCT-CANARY']);
        $accountHash=DB::table('users')->where('id',1)->value('password');
        $path=$this->archived(); $this->service()->restore($this->company(),$path,$this->password);
        $this->prepared(); $this->service()->cancel($this->company());
        $logPath=storage_path('logs/company-archive-'.date('Y-m-d').'.log'); $this->assertFileExists($logPath);
        $log=file_get_contents($logPath);
        foreach (['prepare','verify','remove','restore','cancel'] as $operation) $this->assertStringContainsString('"operation":"'.$operation.'"',$log);
        foreach (['company_archive.operation_started','company_archive.operation_completed','exporting_records','encrypting_zip','saving_zip','restoring_table','database_removal_committed','elapsed_ms','previous_step_ms','peak_memory_bytes','reference'] as $marker) $this->assertStringContainsString($marker,$log);
        foreach ([$this->password,$accountHash,'PRIVATE-PRODUCT-CANARY','one@example.test','archive_sha256','item-images/one.png'] as $secret) $this->assertStringNotContainsString($secret,$log);
        $this->assertSame(2,DB::table('sales')->count()); $this->assertSame('active',CompanyArchiveGate::state(1)->status);
    }

    public function test_archive_logging_failure_does_not_fail_the_operation_and_fallback_redacts_unapproved_fields(): void
    {
        $fallback=storage_path('framework/testing/archive-fallback-'.\Illuminate\Support\Str::uuid().'.log');
        $previous=ini_get('error_log'); ini_set('error_log',$fallback);
        \Illuminate\Support\Facades\Log::shouldReceive('build')->andThrow(new \RuntimeException('private-log-directory'));
        try {
            $result=\App\Services\CompanyArchiveLog::run('diagnostic_test',1,function () {
                \App\Services\CompanyArchiveLog::step('checking_records',['table'=>'items','password'=>'NEVER-LOG-PASSWORD','token'=>'NEVER-LOG-TOKEN','url'=>'NEVER-LOG-URL']);
                return 7;
            });
            $this->assertSame(7,$result); $this->assertFileExists($fallback); $log=file_get_contents($fallback);
            $this->assertStringContainsString('company_archive.log_unavailable',$log); $this->assertStringContainsString('operation_completed',$log);
            foreach (['NEVER-LOG-PASSWORD','NEVER-LOG-TOKEN','NEVER-LOG-URL','private-log-directory'] as $secret) $this->assertStringNotContainsString($secret,$log);
        } finally { ini_set('error_log',$previous); }
    }

    public function test_php_fatal_shutdown_writes_only_safe_archive_context(): void
    {
        $storage=storage_path('framework/testing/archive-fatal-'.\Illuminate\Support\Str::uuid());
        $base=base_path();
        $code='require '.var_export($base.'/vendor/autoload.php',true).'; $app=require '.var_export($base.'/bootstrap/app.php',true).'; '
            .'$app->useStoragePath('.var_export($storage,true).'); $app->make(Illuminate\\Contracts\\Console\\Kernel::class)->bootstrap(); '
            .'set_error_handler(static fn()=>false); App\\Services\\CompanyArchiveLog::run("fatal_test",1,function(){ App\\Services\\CompanyArchiveLog::step("encrypting_zip"); trigger_error("PRIVATE-FATAL-CANARY",E_USER_ERROR); });';
        $process=new \Symfony\Component\Process\Process([PHP_BINARY,'-d','xdebug.mode=off','-d','xdebug.log=','-r',$code],$base,
            ['APP_ENV'=>'testing','DB_CONNECTION'=>'sqlite','DB_DATABASE'=>':memory:','CACHE_STORE'=>'array','SESSION_DRIVER'=>'array','QUEUE_CONNECTION'=>'sync','LOG_CHANNEL'=>'stderr','LARAVEL_STORAGE_PATH'=>$storage]);
        $process->run(); $this->assertNotSame(0,$process->getExitCode());
        $logPath=$storage.'/logs/company-archive-'.date('Y-m-d').'.log'; $this->assertFileExists($logPath); $log=file_get_contents($logPath);
        $this->assertStringContainsString('company_archive.operation_fatal',$log); $this->assertStringContainsString('PHP_FATAL',$log);
        $this->assertStringContainsString('encrypting_zip',$log); $this->assertStringNotContainsString('PRIVATE-FATAL-CANARY',$log);
    }

    public function test_missing_cloud_image_does_not_block_path_only_backup_but_local_files_still_round_trip(): void
    {
        $this->cloudFixture(); Storage::disk('archive_cloud_fixture')->delete('company-one.png');
        DB::table('item_sets')->insert(['id'=>3,'company_id'=>1,'item_id'=>1,'image_disk'=>'public','image_path'=>'item-images/local-extra.png']);
        Storage::disk('public')->put('item-images/local-extra.png','local attachment must survive');
        $path=$this->archived(); $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertCount(1,$manifest['files']); $this->assertCount(1,$manifest['reference_files']);
        $this->assertSame('cloud_paths_only',json_decode(CompanyArchiveGate::state(1)->summary,true)['media_policy']);
        Storage::disk('public')->assertMissing('item-images/local-extra.png');
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame('local attachment must survive',Storage::disk('public')->get('item-images/local-extra.png'));
        Storage::disk('archive_cloud_fixture')->assertMissing('company-one.png');
        $this->assertSame('company-one.png',DB::table('item_sets')->where('id',1)->value('image_path'));
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);
    }

    public function test_cloud_storage_identity_changes_block_restore_without_overwriting_records_or_files(): void
    {
        $this->cloudFixture(); $path=$this->archived();
        config(['filesystems.disks.archive_cloud_fixture.bucket'=>'different-fixture-bucket']);
        $this->rejected(fn()=>$this->service()->restore($this->company(),$path,$this->password),'Cloud image paths or storage identity changed');
        $this->assertSame('archived',CompanyArchiveGate::state(1)->status);
        $this->assertSame(0,DB::table('sales')->where('company_id',1)->count()); $this->assertFalse(DB::table('users')->where('id',3)->exists());
        config(['filesystems.disks.archive_cloud_fixture.bucket'=>'fixture-only']);
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame('active',CompanyArchiveGate::state(1)->status);
        $this->assertSame("remote original\x00image",Storage::disk('archive_cloud_fixture')->get('company-one.png'));
    }

    public function test_missing_local_attachment_identifies_own_record_without_exposing_path_or_other_company(): void
    {
        Storage::disk('public')->delete('item-images/one.png');
        $path=$this->prepared(); $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertStringContainsString('item_sets record #1, field image_path, storage public',array_values($manifest['missing_optional_files'])[0]['source']);
        $inventory=app(CompanyArchiveInventory::class); $refs=$inventory->fileReferences(1); $file=array_values($refs)[0];
        $inventory->fileReferences(null); $hint=$inventory->attachmentSourceHint($file);
        $this->assertStringContainsString('record #1',$hint); $this->assertStringNotContainsString('record #2',$hint);
        $this->assertStringNotContainsString('item-images/one.png',$hint);
        $this->assertSame('ready',CompanyArchiveGate::state(1)->status); $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_missing_company_branch_logos_and_staff_profiles_are_optional_and_restore_preserves_records(): void
    {
        $this->branch(); Schema::table('users',fn(Blueprint $t)=>$t->string('profile_image')->nullable());
        DB::table('companies')->where('id',1)->update(['company_logo'=>'uploads/missing-main-logo.png']);
        DB::table('companies')->where('id',3)->update(['company_logo'=>'uploads/missing-branch-logo.png']);
        DB::table('users')->where('id',3)->update(['profile_image'=>'uploads/missing-staff-profile.png']);
        $path=$this->archived(); $manifest=app(CompanyArchivePackage::class)->inspect($path,$this->password);
        $this->assertSame(5,$manifest['version']); $this->assertCount(3,$manifest['missing_optional_files']);
        $summary=json_decode(CompanyArchiveGate::state(1)->summary,true); $this->assertSame(3,$summary['missing_optional_images']);
        $this->assertFalse(DB::table('users')->where('id',3)->exists()); $this->assertTrue(DB::table('users')->where('id',1)->exists());
        $this->service()->restore($this->company(),$path,$this->password);
        $this->assertSame('uploads/missing-main-logo.png',DB::table('companies')->where('id',1)->value('company_logo'));
        $this->assertSame('uploads/missing-branch-logo.png',DB::table('companies')->where('id',3)->value('company_logo'));
        $this->assertSame('uploads/missing-staff-profile.png',DB::table('users')->where('id',3)->value('profile_image'));
        $this->assertSame(3,DB::table('sales')->count()); Storage::disk('public')->assertMissing('uploads/missing-main-logo.png');
    }

    private function requiredDocument(string $path): void
    {
        Schema::create('customer_orders',function(Blueprint $t){$t->id();$t->foreignId('company_id')->constrained();});
        Schema::create('order_production_files',function(Blueprint $t){$t->id();$t->foreignId('customer_order_id')->constrained('customer_orders');$t->string('path');});
        DB::table('customer_orders')->insert(['id'=>1,'company_id'=>1]);
        DB::table('order_production_files')->insert(['id'=>1,'customer_order_id'=>1,'path'=>$path]);
    }

    public function test_required_production_document_is_not_silently_omitted(): void
    {
        $this->requiredDocument('documents/missing-design.pdf');
        $this->rejected(fn()=>$this->prepared(),'order_production_files record #1');
        $this->assertSame('active',CompanyArchiveGate::state(1)->status??'active'); $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_optional_image_shared_with_required_document_is_still_required(): void
    {
        $this->requiredDocument('shared/missing-source.png');
        DB::table('item_sets')->where('id',1)->update(['image_disk'=>'local','image_path'=>'shared/missing-source.png']);
        $this->rejected(fn()=>$this->prepared(),'attachment is missing');
        $this->assertNull(CompanyArchiveGate::state(1));
    }

    public function test_existing_v4_cloud_path_archive_remains_restorable(): void
    {
        $this->cloudFixture(); $path=$this->prepared(); $package=app(CompanyArchivePackage::class); $manifest=$package->inspect($path,$this->password);
        $work=$package->workspace(); $this->workspaces[]=$work; $sources=[];
        foreach ($manifest['entries'] as $entry=>$meta) {
            $local=$work.'/'.str_replace('/','-',$entry);
            $package->stream($path,$this->password,$entry,function($stream)use($local){file_put_contents($local,stream_get_contents($stream));}); $sources[$entry]=$local;
        }
        $manifest['version']=4; unset($manifest['missing_optional_files']); $legacy=$work.'/legacy-v4.zip'; $package->create($legacy,$this->password,$manifest,$sources);
        DB::table('company_archive_states')->where('company_id',1)->update(['archive_sha256'=>hash_file('sha256',$legacy)]);
        $this->service()->verify($this->company(),$legacy,$this->password); $this->service()->remove($this->company(),$legacy,$this->password);
        $this->service()->restore($this->company(),$legacy,$this->password);
        $this->assertSame('active',CompanyArchiveGate::state(1)->status); $this->assertSame(2,DB::table('sales')->count());
    }

    private function archiveLogEvents(): array
    {
        $events=[]; $file=storage_path('logs/company-archive-'.date('Y-m-d').'.log');
        foreach (file($file,FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (preg_match('/company_archive\.([a-z_]+) (\{.*\})\s*$/',$line,$match)) $events[]=['event'=>$match[1],...json_decode($match[2],true)];
        }
        return $events;
    }

    public function test_api_download_logs_real_stream_start_end_and_keeps_reference_and_archive_id(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/archive-download-'.bin2hex(random_bytes(5)));
        $this->apiAdmin(); $this->prepared(); $id=CompanyArchiveGate::state(1)->archive_id;
        $response=$this->postJson('/api/company/archive/download',['current_password'=>'account-password'])->assertOk();
        $events=array_values(array_filter($this->archiveLogEvents(),fn($event)=>str_starts_with($event['operation'],'download')));
        $this->assertContains('stream_ready',array_column($events,'event'));
        $this->assertNotContains('operation_completed',array_column($events,'event'));
        $this->assertNotContains('download_stream',array_column($events,'operation'));
        $this->assertSame(1,(int)CompanyArchiveGate::state(1)->active_requests);
        $bytes=$response->streamedContent(); $this->assertStringStartsWith('PK',$bytes);
        $events=array_values(array_filter($this->archiveLogEvents(),fn($event)=>str_starts_with($event['operation'],'download')));
        $streams=array_values(array_filter($events,fn($event)=>$event['operation']==='download_stream'));
        $this->assertContains('stream_started',array_column($streams,'stage'));
        $this->assertContains('server_stream_finished',array_column($streams,'stage'));
        $this->assertContains('operation_completed',array_column($streams,'event'));
        $this->assertCount(1,array_unique(array_column($events,'reference')));
        $this->assertSame([$id],array_values(array_unique(array_filter(array_column($events,'archive_id')))));
        $this->assertSame(0,(int)CompanyArchiveGate::state(1)->active_requests);
        $this->assertStringNotContainsString('account-password',file_get_contents(storage_path('logs/company-archive-'.date('Y-m-d').'.log')));
    }

    public function test_web_download_logs_stream_completion_and_deferred_failure_is_not_reported_as_success(): void
    {
        $this->app->useStoragePath(sys_get_temp_dir().'/archive-web-log-'.bin2hex(random_bytes(5)));
        $this->prepared(); $request=Request::create('/company/one/profile/archive/download','POST',['current_password'=>'account-password']);
        $request->setUserResolver(fn()=>User::find(1));
        $response=app(\App\Http\Controllers\Company\CompanyArchiveController::class)->download($request,'one');
        ob_start(); try {$response->sendContent(); $bytes=ob_get_contents();} finally {ob_end_clean();}
        $this->assertStringStartsWith('PK',$bytes);
        $events=array_values(array_filter($this->archiveLogEvents(),fn($event)=>$event['operation']==='download_stream'));
        $this->assertContains('operation_completed',array_column($events,'event'));
        $broken=\App\Services\CompanyArchiveLog::run('broken_download',1,fn()=>response()->stream(static function(){throw new \RuntimeException('PRIVATE-STREAM-FAILURE');}));
        try {($broken->getCallback())(); $this->fail('Expected stream failure.');}
        catch (\RuntimeException $e) {$this->assertSame('PRIVATE-STREAM-FAILURE',$e->getMessage());}
        $brokenEvents=array_values(array_filter($this->archiveLogEvents(),fn($event)=>str_starts_with($event['operation'],'broken_download')));
        $this->assertContains('operation_failed',array_column($brokenEvents,'event'));
        $this->assertNotContains('operation_completed',array_column($brokenEvents,'event'));
        $this->assertStringNotContainsString('PRIVATE-STREAM-FAILURE',file_get_contents(storage_path('logs/company-archive-'.date('Y-m-d').'.log')));
    }

    public function test_legacy_fk_order_fingerprint_is_accepted_but_real_schema_changes_are_rejected_on_reused_service(): void
    {
        $path=$this->prepared(); $native=app(CompanyArchiveInventory::class)->schema();
        $keys=Schema::getForeignKeys('sale_items'); $this->assertCount(2,$keys);
        $inventory=\Mockery::mock(CompanyArchiveInventory::class)->makePartial();
        $inventory->shouldReceive('foreignKeys')->with('sale_items',false)->andReturn(array_reverse($keys));
        $this->assertNotSame($native,$inventory->schema()); $this->assertSame($native,$inventory->schema(true));
        $service=new CompanyArchiveService($inventory,app(CompanyArchivePackage::class));
        $manifest=$service->validated($this->company(),$path,$this->password);
        $this->assertSame(CompanyArchiveGate::state(1)->archive_id,$manifest['archive_id']);
        Schema::table('sales',fn(Blueprint $t)=>$t->string('actual_schema_change')->nullable());
        $this->rejected(fn()=>$service->validated($this->company(),$path,$this->password),'compatibility');
        $this->assertSame(2,DB::table('sales')->count());
    }

    public function test_metadata_refresh_detects_new_tables_on_a_reused_inventory(): void
    {
        $inventory=app(CompanyArchiveInventory::class); $inventory->coverage();
        Schema::create('unreviewed_new_feature',fn(Blueprint $t)=>$t->id());
        $inventory->refreshMetadata();
        $this->rejected(fn()=>$inventory->coverage(),'Unreviewed database tables');
    }
}
