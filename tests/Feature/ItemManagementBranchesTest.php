<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\{Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class ItemManagementBranchesTest extends TestCase
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
        foreach ([1=>null,2=>1,3=>null] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'status'=>1,'parent_company_id'=>$parent]);
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



    public function test_label_export_totals_include_all_selected_rows_not_only_page(): void
    {
        $request=$this->request(['management_branches'=>[1,2],'export'=>'pdf','length'=>1,'start'=>0]);
        $pdf=\Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view,$data) {
            $this->assertCount(2,$data['rows']);
            $totals=array_combine($data['headers'],$data['totals']);
            $this->assertSame('20.000',$totals['Gross Wt']);
            $this->assertSame('2.000',$totals['Other Wt']);
            $this->assertSame('18.000',$totals['Net Wt']);
            $this->assertSame('2',$totals['Quantity']);
            $this->assertSame('0.00',$totals['Other Charges']);
            return true;
        })->andReturn($pdf);
        (new \App\Http\Controllers\Company\ItemSetController)->list_data($request,'branch-1');
    }

    public function test_web_lists_keep_branch_actions_and_print_selection(): void
    {
        $request=$this->request(['management_branches'=>[1,2]]);
        $calls=[
            fn () => (new \App\Http\Controllers\Company\ItemController)->data($request,'branch-1'),
            fn () => (new \App\Http\Controllers\Company\LabelConfigController)->index($request,'branch-1'),
            fn () => (new \App\Http\Controllers\Company\ItemSetController)->list_data($request,'branch-1'),
            fn () => (new \App\Http\Controllers\Company\ItemSetController)->qrList($request,'branch-1'),
        ];
        foreach ($calls as $call) {
            $data=$call()->getData(true);
            $this->assertArrayNotHasKey('error',$data,json_encode($data));
            $this->assertSame(2,$data['recordsTotal']);
            $rows=collect($data['data'])->keyBy('company_id');
            $this->assertSame('Branch 2',$rows[2]['company_name']);
            if (isset($rows[2]['action'])) $this->assertStringContainsString('/company/branch-2/',$rows[2]['action']);
        }
        $request=$this->request(['management_branches'=>[1,2],'only_ids'=>1]);
        $this->assertEqualsCanonicalizing(['1','2'],(new \App\Http\Controllers\Company\ItemSetController)->qrList($request,'branch-1')->getData(true)['ids']);
    }
    public function test_api_pagination_bulk_and_configuration_metadata(): void
    {
        $api=new \App\Http\Controllers\Api\ItemSetController;
        $data=$api->listset_data($this->request(['management_branches'=>[1,2],'per_page'=>1]))->getData(true);
        $this->assertSame(2,$data['pagination']['total']);
        $this->assertCount(1,$data['data']);
        $row=$data['data'][0];
        $this->assertSame('Branch '.$row['company_id'],$row['company_name']);
        $this->assertStringContainsString('company_id='.$row['company_id'],$row['image_upload_url']);
        $data=$api->bulkListsetData($this->request(['management_branches'=>[1,2]]))->getData(true);
        $this->assertCount(2,$data['data']);
        foreach ($data['data'] as $row) $this->assertSame($row['company_id'],$row['details_params']['company_id']);
        $data=(new \App\Http\Controllers\Api\LabelConfigController)->index($this->request(['management_branches'=>[1,2]]))->getData(true);
        $this->assertCount(2,$data['data']);
        $this->assertArrayHasKey('available_items',$data);
        $this->assertSame([1,2],$data['selected_company_ids']);
    }
    public function test_api_exports_all_matching_labels_and_rejects_cross_company_actions(): void
    {
        $api=new \App\Http\Controllers\Api\ItemSetController;
        $response=$api->listset_data($this->request(['management_branches'=>[1,2],'export'=>'excel','per_page'=>1,'page'=>2]));
        $path=$response->getFile()->getPathname(); $zip=new \ZipArchive;
        $this->assertTrue($zip->open($path)); $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Branch 1','Branch 2','L-1','L-2'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('L-3',$xml); $zip->close(); unlink($path);
        $items=new \App\Http\Controllers\Api\ItemController;
        $this->assertSame(404,$items->show($this->request(['company_id'=>2]),1)->getStatusCode());
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $items->destroy($this->request(['management_branches'=>[1,2]]),1);
    }
    public function test_print_api_rejects_labels_outside_selected_branches(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        (new \App\Http\Controllers\Api\ItemLabelPrintController)->pdf($this->request(['management_branches'=>[1,2],'ids'=>[3]]));
    }
    public function test_api_rejects_unrelated_company(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\ItemController)->index($this->request(['company_id'=>3]));
    }

    public function test_print_api_prints_only_authorized_selected_labels(): void
    {
        $pdf=\Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('label-print.pdf')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view,$data) {
            $this->assertSame('company.item_sets.print_pdf',$view);
            $this->assertEqualsCanonicalizing([1,2],$data['itemSets']->pluck('company_id')->all());
            return true;
        })->andReturn($pdf);
        (new \App\Http\Controllers\Api\ItemLabelPrintController)->pdf($this->request(['management_branches'=>[1,2],'ids'=>[1,2]]));
        $this->assertSame(2,DB::table('item_sets')->where('is_printed',1)->count());
        $this->assertSame(0,DB::table('item_sets')->where('id',3)->value('is_printed'));
    }
}
