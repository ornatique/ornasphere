<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CastingBranchSelectionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, name text, slug text, status integer, parent_company_id integer)');
        DB::statement('CREATE TABLE users (id integer primary key, name text, company_id integer)');
        DB::statement('CREATE TABLE vacuum_processes (id integer primary key, name text, company_id integer, created_by integer, created_at text, updated_at text, modified_count integer)');
        DB::statement('CREATE TABLE vacuum_vouchers (id integer primary key, company_id integer, voucher_no text, voucher_date text, vacuum_process_id integer, job_worker_id integer, created_by integer, created_at text, updated_at text)');
        DB::statement('CREATE TABLE vacuum_voucher_items (id integer primary key, vacuum_voucher_id integer, buch_no text, item_name text, remarks text, silver_wt real)');
        DB::statement('CREATE TABLE job_workers (id integer primary key, name text, company_id integer)');
        DB::statement('CREATE TABLE casting_heating_items (id integer primary key, company_id integer, vacuum_voucher_id integer, vacuum_voucher_item_id integer, in_bhati integer, checked_at text, created_at text)');
        foreach ([1=>null,2=>1,3=>null] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'name'=>'Branch '.$id,'slug'=>'branch-'.$id,'status'=>1,'parent_company_id'=>$parent]);
            DB::table('vacuum_processes')->insert(['id'=>$id,'company_id'=>$id,'name'=>'Process '.$id,'created_at'=>now()]);
            DB::table('vacuum_vouchers')->insert(['id'=>$id,'company_id'=>$id,'voucher_no'=>'VV-'.$id,'voucher_date'=>now()->toDateString(),'vacuum_process_id'=>$id,'created_at'=>now()]);
            DB::table('vacuum_voucher_items')->insert(['id'=>$id,'vacuum_voucher_id'=>$id]);
            DB::table('casting_heating_items')->insert(['id'=>$id,'company_id'=>$id,'vacuum_voucher_id'=>$id,'vacuum_voucher_item_id'=>$id,'in_bhati'=>1,'checked_at'=>'2026-10-20 10:00:00']);
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

    public function test_combined_heating_rows_have_branch_stage_data_and_owner_actions(): void
    {
        $request = $this->request(['management_branches'=>[1,2]]);
        $data = (new \App\Http\Controllers\Company\CastingHeatingController)->index($request,'branch-1')->getData(true);
        $this->assertArrayNotHasKey('error', $data, json_encode($data));
        $this->assertSame(2, $data['recordsTotal']);
        $rows = collect($data['data'])->keyBy('company_id');
        $this->assertSame('Branch 2', $rows[2]['company_name']);
        $this->assertStringContainsString('20-10-2026', $rows[2]['date_time_view']);
        $this->assertStringContainsString('/company/branch-2/', $rows[2]['action']);
        $this->assertSame(1, $rows[2]['in_bhati_pcs']);
    }

    public function test_excel_exports_all_selected_rows_instead_of_current_page(): void
    {
        $request = $this->request(['management_branches'=>[1,2], 'export'=>'excel', 'start'=>1, 'length'=>1], false);
        $response = (new \App\Http\Controllers\Company\CastingHeatingController)->index($request,'branch-1');
        $zip = new \ZipArchive;
        $path = $response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Company / Branch', $xml);
        foreach (['Branch 1','Branch 2','VV-1','VV-2'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('VV-3',$xml);
        $zip->close();
        unlink($path);
    }

    public function test_pdf_uses_combined_branch_heading_and_rows(): void
    {
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->with('a4','landscape')->andReturnSelf();
        $pdf->shouldReceive('download')->once()->with('casting-report.pdf')->andReturn(response('pdf'));
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()->withArgs(function ($view,$data) {
            $this->assertSame('company.casting_exports.list',$view);
            $this->assertSame('Branch 1 / Branch 2',$data['branches']);
            $this->assertSame('Company / Branch',$data['headers'][0]);
            $this->assertCount(2,$data['rows']);
            $this->assertEqualsCanonicalizing(['Branch 1','Branch 2'],array_column($data['rows'],0));
            return true;
        })->andReturn($pdf);
        $request=$this->request(['management_branches'=>[1,2],'export'=>'pdf','length'=>1],false);
        (new \App\Http\Controllers\Company\CastingHeatingController)->index($request,'branch-1');
    }

    public function test_all_casting_stage_queries_correlate_company_and_voucher(): void
    {
        foreach (['CastingMetalIssue','CastingRelease','TreeCuttingOffice','TreeCuttingIssue','TreeCuttingReceive','CastingSorting'] as $name) {
            $request=$this->request(['management_branches'=>[1,2]]);
            \Yajra\DataTables\Facades\DataTables::shouldReceive('of')->once()->andReturnUsing(function ($query) {
                $sql=$query->toSql();
                $this->assertStringContainsString('"company_id" in (?, ?)', $sql);
                $this->assertStringContainsString('= "vacuum_vouchers"."company_id"', $sql);
                $this->assertStringNotContainsString('."company_id" = ?', $sql);
                throw new \RuntimeException('query verified');
            });
            $class='App\\Http\\Controllers\\Company\\'.$name.'Controller';
            try { (new $class)->index($request,'branch-1'); $this->fail('No list query'); }
            catch (\RuntimeException $e) { $this->assertSame('query verified',$e->getMessage()); }
        }
    }

    public function test_history_exports_each_voucher_with_its_branch(): void
    {
        foreach (['casting_metal_issue_items','casting_release_items','tree_cutting_office_items','tree_cutting_issue_items','tree_cutting_receive_items','casting_sorting_items'] as $table) {
            DB::statement('CREATE TABLE '.$table.' (id integer primary key, company_id integer, vacuum_voucher_id integer, vacuum_voucher_item_id integer, is_custom integer)');
        }
        $request=$this->request(['management_branches'=>[1,2],'export'=>'excel'],false);
        $response=(new \App\Http\Controllers\Company\VoucherHistoryController)->index($request,'branch-1');
        $zip=new \ZipArchive;
        $path=$response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Branch 1','Branch 2','VV-1','VV-2','Casting Heating'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('VV-3',$xml);
        $zip->close(); unlink($path);
        $api=new \App\Http\Controllers\Api\VoucherHistoryApiController;
        $data=$api->index($this->request(['management_branches'=>[1,2],'voucher_id'=>2]))->getData(true);
        $this->assertCount(2,$data['data']['vouchers']);
        $this->assertSame(2,$data['data']['voucher']['company_id']);
        $response=$api->index($this->request(['management_branches'=>[1,2],'export'=>'excel']));
        $path=$response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Branch 1','Branch 2','VV-1','VV-2'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('VV-3',$xml);
        $zip->close();unlink($path);
    }

    public function test_pdf_template_renders_a_valid_pdf(): void
    {
        $request=$this->request(['management_branches'=>[1,2],'export'=>'pdf'],false);
        $response=(new \App\Http\Controllers\Company\CastingHeatingController)->index($request,'branch-1');
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertGreaterThan(1000,strlen($response->getContent()));
        if (getenv('CASTING_PDF_PREVIEW')) file_put_contents(base_path('tmp/casting-branches-preview.pdf'),$response->getContent());
    }

    public function test_main_admin_can_open_own_casting_branch_routes(): void
    {
        foreach (['VacuumBuch','VacuumProcess','VacuumVoucher','CastingHeating','CastingMetalIssue','CastingRelease','TreeCuttingOffice','TreeCuttingIssue','TreeCuttingReceive','CastingSorting','VoucherHistory'] as $name) {
            $request=$this->request();
            $class='App\\Http\\Controllers\\Company\\'.$name.'Controller';
            $route=new \Illuminate\Routing\Route('GET','company/{slug}/casting-heating',['uses'=>$class.'@index','controller'=>$class.'@index']);
            $route->bind($request);
            $route->setParameter('slug','branch-2');
            $request->setRouteResolver(fn () => $route);
            $response=(new \App\Http\Middleware\EnsureCompanyIsActive)->handle($request,fn () => response('allowed'));
            $this->assertSame('allowed',$response->getContent());
        }
    }

    public function test_casting_api_lists_and_exports_are_branch_scoped(): void
    {
        $controller=new \App\Http\Controllers\Api\CastingHeatingApiController;
        foreach ([[[],[1]], [['management_branches'=>[1,2]],[1,2]], [['company_id'=>2],[2]]] as [$input,$ids]) {
            $data=$controller->index($this->request($input))->getData(true);
            $this->assertEqualsCanonicalizing($ids,array_column($data['data'],'company_id'));
            $this->assertEqualsCanonicalizing($ids,$data['selected_company_ids']);
            foreach ($data['data'] as $row) $this->assertSame('Branch '.$row['company_id'],$row['company_name']);
        }
        $response=$controller->index($this->request(['management_branches'=>[1,2],'export'=>'excel']));
        $path=$response->getFile()->getPathname();
        $zip=new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Branch 1','Branch 2','VV-1','VV-2'] as $value) $this->assertStringContainsString($value,$xml);
        $this->assertStringNotContainsString('VV-3',$xml);
        $zip->close();unlink($path);
        $pdf=$controller->index($this->request(['management_branches'=>[1,2],'export'=>'pdf']));
        $this->assertStringStartsWith('%PDF-',$pdf->getContent());
    }

    public function test_casting_api_action_updates_only_selected_company(): void
    {
        DB::statement('ALTER TABLE vacuum_processes ADD COLUMN updated_by integer');
        $controller=new \App\Http\Controllers\Api\VacuumProcessApiController;
        $this->assertSame(404,$controller->show($this->request(),2)->getStatusCode());
        $this->assertSame(200,$controller->show($this->request(['company_id'=>2]),2)->getStatusCode());
        $result=$controller->update($this->request(['company_id'=>2,'name'=>'Changed branch process']),2);
        $this->assertSame(200,$result->getStatusCode());
        $this->assertSame('Changed branch process',DB::table('vacuum_processes')->where('id',2)->value('name'));
        $this->assertSame('Process 1',DB::table('vacuum_processes')->where('id',1)->value('name'));
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->update($this->request(['management_branches'=>[1,2],'name'=>'Invalid']),2);
    }

    public function test_casting_api_rejects_other_company(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\CastingHeatingApiController)->index($this->request(['management_branches'=>[1,3]]));
    }

    public function test_unrelated_branch_is_rejected_before_export(): void
    {
        $request=$this->request(['management_branches'=>[1,3],'export'=>'excel'],false);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Company\CastingHeatingController)->index($request,'branch-1');
    }
}
