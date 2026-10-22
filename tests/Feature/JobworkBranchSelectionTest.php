<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Company, User};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class JobworkBranchSelectionTest extends TestCase
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
        DB::statement('CREATE TABLE labour_formulas (id integer primary key, company_id integer, name text, status integer)');
        foreach ([1=>null,2=>1,3=>null] as $id=>$parent) {
            DB::table('labour_formulas')->insert(['id'=>$id,'company_id'=>$id,'name'=>'Formula '.$id,'status'=>1]);
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


    public function test_combined_list_and_actions_use_record_owner(): void
    {
        $data=(new \App\Http\Controllers\Company\LabourFormulaController)->index($this->request(['management_branches'=>[1,2]]),'branch-1')->getData(true);
        $this->assertArrayNotHasKey('error',$data,json_encode($data));
        $this->assertSame(2,$data['recordsTotal']);
        $rows=collect($data['data'])->keyBy('company_id');
        $this->assertSame('Branch 2',$rows[2]['company_name']);
        $this->assertStringContainsString('/company/branch-2/',$rows[2]['action']);
    }
    public function test_excel_contains_all_selected_branches_and_excludes_unrelated_company(): void
    {
        $request=$this->request(['management_branches'=>[1,2],'export'=>'excel','start'=>1,'length'=>1],false);
        $response=(new \App\Http\Controllers\Company\LabourFormulaController)->index($request,'branch-1');
        $zip=new \ZipArchive;
        $path=$response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Company / Branch','Branch 1','Branch 2','Formula 1','Formula 2'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('Formula 3',$xml);
        $zip->close(); unlink($path);
    }
    public function test_unrelated_branch_is_rejected(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Company\LabourFormulaController)->index($this->request(['management_branches'=>[3]]),'branch-1');
    }

    public function test_receive_combines_issue_and_direct_rows_with_owner_actions(): void
    {
        DB::statement('CREATE TABLE production_steps (id integer primary key, company_id integer, name text)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, name text)');
        DB::statement('CREATE TABLE jobwork_issues (id integer primary key, company_id integer, voucher_no text, jobwork_date text, job_worker_id integer, production_step_id integer, created_at text)');
        DB::statement('CREATE TABLE jobwork_issue_items (id integer primary key, jobwork_issue_id integer, net_wt real)');
        DB::statement('CREATE TABLE jobwork_receives (id integer primary key, company_id integer, jobwork_issue_id integer, receive_type text, receive_no text, receive_date text, job_worker_id integer, customer_id integer, production_step_id integer, created_at text)');
        DB::statement('CREATE TABLE jobwork_receive_items (id integer primary key, jobwork_receive_id integer, receive_net_wt real, receive_qty_pcs integer, loss_wt real)');
        foreach ([1,2,3] as $id) {
            DB::table('jobwork_issues')->insert(['id'=>$id,'company_id'=>$id,'voucher_no'=>'JI-'.$id,'jobwork_date'=>'2026-10-21','created_at'=>'2026-10-21 10:00:00']);
            DB::table('jobwork_issue_items')->insert(['id'=>$id,'jobwork_issue_id'=>$id,'net_wt'=>10]);
            DB::table('jobwork_receives')->insert(['id'=>$id,'company_id'=>$id,'receive_type'=>'direct','receive_no'=>'DR-'.$id,'receive_date'=>'2026-10-21','created_at'=>'2026-10-21 11:00:00']);
            DB::table('jobwork_receive_items')->insert(['id'=>$id,'jobwork_receive_id'=>$id,'receive_net_wt'=>5]);
        }
        DB::statement('CREATE TABLE items (id integer primary key, item_name text, item_code text)');
        foreach (['item_id integer','receive_gross_wt real','other_wt real','receive_fine_wt real','total_amount real'] as $column) DB::statement('ALTER TABLE jobwork_receive_items ADD COLUMN '.$column);
        $api=new \App\Http\Controllers\Api\JobworkReceiveApiController;
        $apiData=$api->index($this->request(['management_branches'=>[1,2]]))->getData(true);
        $this->assertCount(4,$apiData['data']);
        $this->assertSame([1,2],$apiData['selected_company_ids']);
        $this->assertCount(2,$api->directIndex($this->request(['management_branches'=>[1,2]]))->getData(true)['data']);
        $controller=new \App\Http\Controllers\Company\JobworkReceiveController;
        $data=$controller->index($this->request(['management_branches'=>[1,2]]),'branch-1')->getData(true);
        $this->assertSame(4,$data['recordsTotal']);
        foreach ($data['data'] as $row) {
            $this->assertContains($row['company_id'],[1,2]);
            $this->assertStringContainsString('/company/branch-'.$row['company_id'].'/',$row['action']);
            $this->assertSame('Branch '.$row['company_id'],$row['company_name']);
        }
        $data=$controller->index($this->request(['management_branches'=>[1,2],'status'=>'direct']),'branch-1')->getData(true);
        $this->assertSame(2,$data['recordsTotal']);
        $response=$controller->index($this->request(['management_branches'=>[1,2],'export'=>'excel','length'=>1],false),'branch-1');
        $zip=new \ZipArchive;
        $path=$response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['JI-1','JI-2','DR-1','DR-2','Branch 1','Branch 2'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('DR-3',$xml);
        $zip->close(); unlink($path);
    }

    public function test_api_lists_exports_and_updates_are_scoped_to_selected_company(): void
    {
        DB::statement('ALTER TABLE labour_formulas ADD COLUMN created_at text');
        DB::statement('ALTER TABLE labour_formulas ADD COLUMN updated_at text');
        $controller=new \App\Http\Controllers\Api\LabourFormulaApiController;
        $data=$controller->index($this->request(['management_branches'=>[1,2]]))->getData(true);
        $this->assertSame([1,2],$data['selected_company_ids']);
        $this->assertCount(2,$data['data']);
        $this->assertEqualsCanonicalizing(['Branch 1','Branch 2'],array_column($data['data'],'company_name'));
        $request=$this->request(['company_id'=>2,'name'=>'Changed branch formula','status'=>1]);
        $this->assertSame(200,$controller->update($request,2)->getStatusCode());
        $this->assertSame('Changed branch formula',DB::table('labour_formulas')->where('id',2)->value('name'));
        $this->assertSame('Formula 1',DB::table('labour_formulas')->where('id',1)->value('name'));
        $this->assertSame(1,$request->user()->company_id);
        $this->assertSame(404,$controller->show($this->request(['company_id'=>2]),1)->getStatusCode());
        $response=$controller->index($this->request(['management_branches'=>[1,2],'export'=>'excel']));
        $zip=new \ZipArchive; $path=$response->getFile()->getPathname();
        $this->assertTrue($zip->open($path));
        $xml=$zip->getFromName('xl/worksheets/sheet1.xml');
        foreach (['Branch 1','Branch 2','Changed branch formula'] as $text) $this->assertStringContainsString($text,$xml);
        $this->assertStringNotContainsString('Formula 3',$xml);
        $zip->close(); unlink($path);
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $controller->update($this->request(['management_branches'=>[1,2],'name'=>'Invalid']),2);
    }
}
