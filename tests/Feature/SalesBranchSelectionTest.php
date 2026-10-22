<?php
namespace Tests\Feature;
use App\Models\{Company,User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SalesBranchSelectionTest extends ApiItemReceiptTest
{
    protected function setUp(): void
    {
        parent::setUp();
        DB::statement('ALTER TABLE companies ADD COLUMN slug text');
        DB::statement('ALTER TABLE companies ADD COLUMN parent_company_id integer');
        DB::statement('ALTER TABLE companies ADD COLUMN status integer');
        DB::table('companies')->where('id',1)->update(['slug'=>'main','status'=>1]);
        foreach ([2=>1,3=>null] as $id=>$parent) DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'parent_company_id'=>$parent,'status'=>1]);
    }
    private function scopedRequest(array $input): Request
    {
        $request=Request::create('/api/sales/advance-ledger/vouchers','GET',$input,[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $this->app->instance('request',$request);
        $user=\Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>1,'company_id'=>1]); $user->setRelation('company',Company::find(1));
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        $request->setUserResolver(fn ()=>$user);
        return $request;
    }
    public function test_combined_vouchers_have_global_pagination_and_export(): void
    {
        foreach ([1,2,3] as $id) DB::table('customer_advance_vouchers')->insert(['company_id'=>$id,'customer_id'=>180,'voucher_no'=>'RP-'.$id,'voucher_date'=>'2026-10-21','entry_type'=>'receive_amount','cash_in'=>100,'created_at'=>now(),'updated_at'=>now()]);
        $controller=new \App\Http\Controllers\Api\CustomerAdvanceApiController;
        $data=$controller->vouchers($this->scopedRequest(['management_branches'=>[1,2],'per_page'=>1]))->getData(true);
        $this->assertSame(2,$data['pagination']['total']); $this->assertCount(1,$data['data']);
        $this->assertSame('Branch 2',$data['data'][0]['company_name']);
        $response=$controller->vouchers($this->scopedRequest(['management_branches'=>[1,2],'export'=>'excel','per_page'=>1,'page'=>2]));
        $path=$response->getFile()->getPathname(); $zip=new \ZipArchive; $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['RP-1','RP-2','Branch 2'] as $value) $this->assertStringContainsString($value,$xml);
        $this->assertStringNotContainsString('RP-3',$xml); $zip->close();unlink($path);
    }
    public function test_foreign_company_is_rejected(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\CustomerAdvanceApiController)->vouchers($this->scopedRequest(['company_id'=>3]));
    }
    public function test_multi_company_write_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new \App\Http\Controllers\Api\CustomerAdvanceApiController)->store($this->scopedRequest(['management_branches'=>[1,2]]));
    }
}
