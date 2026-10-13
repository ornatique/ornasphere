<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Company;
use App\Services\VoucherSeries;
use App\Http\Controllers\Company\VoucherSeriesController;
use Illuminate\Support\Facades\{DB,Schema};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;

class VoucherSeriesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        Schema::create('companies',function(Blueprint $t){$t->id();$t->string('name');$t->string('slug');$t->timestamps();});
        foreach(['sales'=>'voucher_no','approval_headers'=>'approval_no','sale_returns'=>'return_voucher_no',
            'customer_advance_vouchers'=>'voucher_no','jobwork_issues'=>'voucher_no',
            'jobwork_receives'=>'receive_no','vacuum_vouchers'=>'voucher_no'] as $table=>$column) {
            Schema::create($table,function(Blueprint $t) use($table,$column){
                $t->id();$t->unsignedBigInteger('company_id');$t->string($column);
                if(in_array($table,['sales','approval_headers','customer_advance_vouchers'])) $t->unique($column);
            });
        }
        (require database_path('migrations/2026_10_06_000002_add_company_voucher_series.php'))->up();
        Company::forceCreate(['id'=>1,'name'=>'Madina Jwellary','slug'=>'madina']);
        Company::forceCreate(['id'=>2,'name'=>'Madina Jewels','slug'=>'other']);
    }

    public function test_defaults_independent_modules_and_company_scoped_unique_numbers(): void
    {
        foreach(array_keys(VoucherSeries::MODULES) as $module) {
            $this->assertSame('MJ-'.$module.'-001',VoucherSeries::next(1,$module));
            $this->assertSame('MJ-'.$module.'-002',VoucherSeries::next(1,$module));
        }
        $this->assertSame('MJ-SL-001',VoucherSeries::next(2,'SL'));
        foreach(['sales'=>'voucher_no','approval_headers'=>'approval_no','customer_advance_vouchers'=>'voucher_no'] as $table=>$column) {
            DB::table($table)->insert([['company_id'=>1,$column=>'MJ-001'],['company_id'=>2,$column=>'MJ-001']]);
            $this->assertSame(2,DB::table($table)->count());
        }
    }

    public function test_prefix_change_continues_counter_and_deletion_does_not_reuse_number(): void
    {
        $first=VoucherSeries::next(1,'SL');
        DB::table('sales')->insert(['company_id'=>1,'voucher_no'=>$first]);
        Company::find(1)->forceFill(['voucher_prefix'=>'SJ'])->save();
        $this->assertSame('SJ-SL-002',VoucherSeries::next(1,'SL'));
        $this->assertSame('MJ-SL-001',DB::table('sales')->value('voucher_no'));
        DB::table('sales')->delete();
        Company::find(1)->forceFill(['voucher_prefix'=>null])->save();
        $this->assertSame('MJ-SL-003',VoucherSeries::next(1,'SL'));
    }

    public function test_existing_numbers_are_skipped_and_preview_does_not_allocate(): void
    {
        DB::table('sales')->insert(['company_id'=>1,'voucher_no'=>'MJ-SL-001']);
        $config=VoucherSeries::configuration(Company::find(1));
        $this->assertSame('MJ-SL-002',$config['series'][0]['preview']);
        $this->assertSame(0,DB::table('company_voucher_counters')->count());
        $this->assertSame('MJ-SL-002',VoucherSeries::next(1,'SL'));
        DB::table('company_voucher_counters')->where('company_id',1)->where('module','SL')->update(['last_number'=>999]);
        $this->assertSame('MJ-SL-1000',VoucherSeries::next(1,'SL'));
    }

    public function test_failed_voucher_transaction_rolls_back_allocation(): void
    {
        try {
            DB::transaction(function(){VoucherSeries::next(1,'APP');throw new \RuntimeException('Failed save');});
        } catch(\RuntimeException $e) {}
        $this->assertSame('MJ-APP-001',VoucherSeries::next(1,'APP'));
    }

    public function test_configuration_api_restricts_changes_to_company_admin(): void
    {
        $user=new class {public $company_id=1;public $admin=true;public function hasRole($role){return $this->admin;}};
        $r=Request::create('/','PUT',['prefix'=>'sj']);$r->setUserResolver(fn()=>$user);
        $controller=new VoucherSeriesController;
        $result=$controller->update($r)->getData(true);
        $this->assertSame('SJ',$result['data']['prefix']);
        $this->assertNull(Company::find(2)->voucher_prefix);
        $r->merge(['prefix'=>'']);
        $this->assertSame('MJ',$controller->update($r)->getData(true)['data']['prefix']);
        $user->admin=false;
        try {$controller->update($r);$this->fail('Staff edited series');}
        catch(\Symfony\Component\HttpKernel\Exception\HttpException $e){$this->assertSame(403,$e->getStatusCode());}
    }
}
