<?php
namespace Tests\Feature;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\User;
use App\Http\Controllers\Company\CompanyProfileController;
class CompanyOpeningBalancesTest extends TestCase
{
    public function test_main_company_saves_and_branch_is_rejected(): void
    {
        DB::statement('CREATE TABLE companies (id integer primary key, slug text, parent_company_id integer, opening_balance_date text, opening_cash real, opening_bank real, opening_gold_fine real, opening_silver_fine real, updated_at text)');
        DB::table('companies')->insert([['id'=>1,'slug'=>'main','parent_company_id'=>null],['id'=>2,'slug'=>'branch','parent_company_id'=>1]]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['company_id'=>1]);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        $request = Request::create('/', 'PUT', ['opening_balance_date'=>'2026-10-19','opening_cash'=>50000,'opening_bank'=>25000,'opening_gold_fine'=>10.123,'opening_silver_fine'=>455.726]);
        $request->setUserResolver(fn()=>$user);
        (new CompanyProfileController)->openingBalances($request,'main');
        $this->assertEquals(455.726, DB::table('companies')->where('id',1)->value('opening_silver_fine'));
        $this->assertEquals(50000, DB::table('companies')->where('id',1)->value('opening_cash'));
        $api = new \App\Http\Controllers\Api\CompanyOpeningBalanceController;
        $response = $api->show($request)->getData(true);
        $this->assertSame('455.726', $response['data']['opening_silver_fine']);
        $request->merge(['opening_cash'=>60000]);
        $response = $api->update($request)->getData(true);
        $this->assertSame('60000.00', $response['data']['opening_cash']);
        $user->forceFill(['company_id'=>2]);
        try { (new CompanyProfileController)->openingBalances($request,'branch'); $this->fail('Branch accepted'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(403,$e->getStatusCode()); }
        $this->assertNull(DB::table('companies')->where('id',2)->value('opening_cash'));
        foreach (['show','update'] as $method) {
            try { $api->$method($request); $this->fail('Branch API accepted'); }
            catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertEquals(403,$e->getStatusCode()); }
        }
    }
}
