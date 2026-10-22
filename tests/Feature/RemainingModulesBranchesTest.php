<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class RemainingModulesBranchesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, name text, slug text, status integer, parent_company_id integer)');
        DB::statement('CREATE TABLE items (id integer primary key, company_id integer, item_name text, item_code text, metal text, labour_type text, is_active integer, created_at text, updated_at text)');
        DB::statement('CREATE TABLE item_labels (id integer primary key, item_id integer, company_id integer)');
        DB::statement('CREATE TABLE label_configs (id integer primary key, item_id integer, company_id integer, prefix text, created_at text)');
        DB::statement('CREATE TABLE item_sets (id integer primary key, company_id integer, item_id integer, serial_no integer, qr_code text, barcode text, HUID text, gross_weight real, other real, net_weight real, sale_labour_formula text, sale_labour_rate real, sale_labour_amount real, sale_other real, is_printed integer, is_final integer, created_at text, updated_at text, printed_at text, image_disk text, image_path text, image_mime text, image_size integer, image_uploaded_at text)');
        DB::statement('ALTER TABLE item_sets ADD COLUMN is_sold integer DEFAULT 0');
        DB::statement('CREATE TABLE users (id integer primary key, name text)');
        DB::statement('CREATE TABLE marketing_qrs (id integer primary key, company_id integer, created_by integer, title text, type text, size integer, web_url text, logo_path text, created_at text)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, category_person_id integer, name text)');
        DB::statement('CREATE TABLE customer_advance_ledgers (id integer primary key, company_id integer, customer_id integer, cash_in real, cash_out real, metal_in real, metal_out real, metal_type text, entry_date text, created_at text)');
        foreach ([1=>null,2=>1,3=>null] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'status'=>1,'parent_company_id'=>$parent]);
            DB::table('marketing_qrs')->insert(['id'=>$id,'company_id'=>$id,'title'=>'QR '.$id,'type'=>'web','size'=>250,'web_url'=>'https://example.com','created_at'=>now()]);
            DB::table('category_people')->insert(['id'=>$id,'company_id'=>$id,'category_name'=>'bullion']);
            DB::table('customers')->insert(['id'=>$id,'company_id'=>$id,'category_person_id'=>$id,'name'=>'Person '.$id]);
            DB::table('customer_advance_ledgers')->insert(['id'=>$id,'company_id'=>$id,'customer_id'=>$id,'cash_in'=>$id*100,'cash_out'=>0,'metal_in'=>0,'metal_out'=>0,'entry_date'=>'2026-10-21']);
            DB::table('items')->insert(['id'=>$id,'company_id'=>$id,'item_name'=>'Item '.$id,'item_code'=>'I'.$id,'created_at'=>now()]);
            DB::table('label_configs')->insert(['id'=>$id,'company_id'=>$id,'item_id'=>$id,'prefix'=>'L'.$id,'created_at'=>now()]);
            DB::table('item_sets')->insert(['id'=>$id,'company_id'=>$id,'item_id'=>$id,'qr_code'=>'L-'.$id,'serial_no'=>1,'is_final'=>1,'is_printed'=>0,'gross_weight'=>10,'other'=>1,'net_weight'=>9,'sale_other'=>0,'created_at'=>now(),'updated_at'=>now()]);
        }
    }
    private function request(array $input = [], bool $ajax = true): Request
    {
        $request = Request::create('/company/branch-1/casting-heating','GET', $input, [], [], $ajax ? ['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest'] : []);
        $this->app->instance('request', $request);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>1,'company_id'=>1,'is_active'=>1]);
        $user->setRelation('company', Company::findOrFail(1));
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        $request->setUserResolver(fn () => $user);
        return $request;
    }




    public function test_gallery_combines_branches_with_global_pagination_and_owner_links(): void
    {
        $request=$this->request(['management_branches'=>[1,2],'per_page'=>1]);
        $web=(new \App\Http\Controllers\Company\StockGalleryController)->data($request,'branch-1')->getData(true);
        $this->assertSame(2,$web['total']);
        $this->assertCount(1,$web['data']);
        $this->assertSame(2,$web['data'][0]['company_id']);
        $this->assertFalse($web['data'][0]['can_manage']);
        $this->assertStringContainsString('/branch-2/',$web['data'][0]['download_url']);
        $this->assertSame(2,$web['summary']['total_items']);
        $api=(new \App\Http\Controllers\Api\StockGalleryApiController)->index($request)->getData(true);
        $this->assertSame(2,$api['pagination']['total']);
        $this->assertSame('Branch 2',$api['data'][0]['company_name']);
        $this->assertStringContainsString('company_id=2',$api['data'][0]['image_download_url']);
    }
    public function test_qr_list_combines_and_branch_mutation_is_denied(): void
    {
        $request=$this->request(['management_branches'=>[1,2]]);
        $route=new \Illuminate\Routing\Route(['GET'],'test',['uses'=>'App\\Http\\Controllers\\Api\\QrMakingApiController@handle']);
        $route->bind($request); $route->setParameter('operation','index');
        $request->setRouteResolver(fn ()=>$route);
        $api=new \App\Http\Controllers\Api\QrMakingApiController;
        $data=$api->handle($request)->getData(true);
        $this->assertSame(2,$data['data']['total']);
        $rows=collect($data['data']['data'])->keyBy('company_id');
        $this->assertFalse($rows[2]['can_manage']);
        $this->assertStringContainsString('company_id=2',$rows[2]['pdf_url']);
        $request=$this->request(['company_id'=>2]);
        $route->setParameter('operation','destroy'); $route->setParameter('id',2);
        $request->setRouteResolver(fn ()=>$route);
        $this->assertSame(403,$api->handle($request)->getStatusCode());
        $this->assertSame(3,DB::table('marketing_qrs')->count());
    }
    public function test_bullion_people_and_balances_stay_with_the_owner(): void
    {
        $api=new \App\Http\Controllers\Api\BullionApiController;
        $request=$this->request(['management_branches'=>[1,2]]);
        $people=$api->persons($request)->getData(true)['data'];
        $this->assertCount(2,$people);
        $this->assertSame('Branch 2',$people[1]['company_name']);
        $request=$this->request(['management_branches'=>[1,2],'person_id'=>2]);
        $balance=$api->balance($request)->getData(true)['data'];
        $this->assertSame(2,$balance['company_id']);
        $this->assertEquals(200,$balance['balance']['cash']);
        $request=$this->request(['management_branches'=>[1,2],'customer_id'=>\Illuminate\Support\Facades\Crypt::encryptString('2')]);
        $view=(new \App\Http\Controllers\Company\BullionController)->index($request,'branch-1')->getData();
        $this->assertSame(2,$view['ledgerCompany']->id);
        $this->assertEquals(200,$view['balance']['cash']);
    }
    public function test_gallery_pdf_link_preserves_selection_and_pdf_labels_branches(): void
    {
        $request=$this->request(['management_branches'=>[1,2]]);
        $api=new \App\Http\Controllers\Api\StockGalleryApiController;
        $result=$api->exportPdf($request)->getData(true);
        parse_str(parse_url($result['pdf_url'], PHP_URL_QUERY), $query);
        $this->assertEquals([1,2],$query['management_branches']);
        $pdf=\Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view,$data) {
            $this->assertSame('Branch 1 / Branch 2',$data['branchNames']);
            return $view==='company.stock_gallery.pdf';
        })->andReturn($pdf);
        $request->merge(['direct'=>1]);
        $this->assertSame('pdf',$api->exportPdf($request)->getContent());
    }
    public function test_branch_staff_cannot_select_main_company(): void
    {
        $request=$this->request(['management_branches'=>[1,2]]);
        $user=$request->user(); $user->company_id=2; $user->setRelation('company', Company::findOrFail(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\BullionApiController)->persons($request);
    }
    public function test_unrelated_branch_is_rejected(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\StockGalleryApiController)->index($this->request(['management_branches'=>[1,3]]));
    }
}

