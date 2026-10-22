<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Company,User};
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
class VisitingCardBranchesTest extends TestCase
{
    private function req(array $input): Request {
        $r=Request::create('/','GET',$input,[],[],['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $u=\Mockery::mock(User::class)->makePartial(); $u->forceFill(['id'=>1,'company_id'=>1]);
        $u->setRelation('company',Company::find(1)); $u->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        $this->app->instance('request',$r); $r->setUserResolver(fn()=>$u); return $r;
    }
    protected function setUp(): void {
        parent::setUp(); $this->assertSame(':memory:',config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, name text, slug text, status integer, parent_company_id integer)');
        DB::statement('CREATE TABLE visiting_cards (id integer primary key, company_id integer, name text, mobile_no text, email text, city text, pincode text, address text, image_path text, created_at text, updated_at text)');
        foreach ([1=>null,2=>1,3=>null] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'status'=>1,'parent_company_id'=>$parent]);
            DB::table('visiting_cards')->insert(['id'=>$id,'company_id'=>$id,'name'=>'Card '.$id,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    public function test_combined_web_and_api_lists_and_readonly_actions(): void {
        $r=$this->req(['management_branches'=>[1,2]]);
        $web=(new \App\Http\Controllers\Company\ReportController)->visitingCards($r,'branch-1')->getData(true);
        $this->assertSame(2,$web['recordsTotal']);
        $row=collect($web['data'])->firstWhere('company_id',2);
        $this->assertSame('Branch 2',$row['company_name']); $this->assertStringContainsString('disabled',$row['action']);
        $api=(new \App\Http\Controllers\Api\VisitingCardApiController)->index($r)->getData(true);
        $this->assertSame(2,$api['data']['total']); $this->assertFalse($api['data']['data'][0]['can_manage']);
    }
    public function test_export_keeps_branch_and_search_filters(): void {
        $r=$this->req(['management_branches'=>[1,2],'search'=>'Card 2']);
        $pdf=\Mockery::mock(\Barryvdh\DomPDF\PDF::class); $pdf->shouldReceive('setPaper')->andReturnSelf(); $pdf->shouldReceive('download')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function($view,$data) {
            $this->assertCount(1,$data['rows']); $this->assertSame(2,$data['rows'][0]->company_id);
            $this->assertSame('Branch 1 / Branch 2',$data['branchNames']); return true;
        })->andReturn($pdf);
        (new \App\Http\Controllers\Company\ReportController)->visitingCardsPdf($r,'branch-1');
    }
    public function test_unrelated_company_is_rejected(): void {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\VisitingCardApiController)->index($this->req(['company_id'=>3]));
    }
}

