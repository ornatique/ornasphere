<?php
namespace Tests\Feature;

use App\Services\WorkerMetalLedger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WorkerMetalLedgerTest extends TestCase
{
    public function test_opening_partial_receipts_unreceived_issues_and_company_isolation(): void
    {
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        foreach ([
            'job_workers' => 'id integer, company_id integer, name text',
            'items' => 'id integer, item_name text, metal text',
            'jobwork_issues' => 'id integer, company_id integer, job_worker_id integer, jobwork_date text, voucher_no text',
            'jobwork_issue_items' => 'id integer, jobwork_issue_id integer, item_id integer, net_wt real, fine_wt real',
            'jobwork_receives' => 'id integer, company_id integer, jobwork_issue_id integer, job_worker_id integer, receive_date text, receive_no text',
            'jobwork_receive_items' => 'id integer, jobwork_receive_id integer, jobwork_issue_item_id integer, item_id integer, receive_net_wt real, receive_fine_wt real',
        ] as $table => $columns) DB::statement("CREATE TABLE $table ($columns)");
        DB::table('job_workers')->insert([['id'=>1,'company_id'=>1,'name'=>'Demo Worker'],['id'=>2,'company_id'=>2,'name'=>'Other Company']]);
        DB::table('items')->insert([['id'=>1,'item_name'=>'Chain','metal'=>'Silver'],['id'=>2,'item_name'=>'Ring','metal'=>'Gold']]);
        foreach ([[1,1,1,'2026-09-30',500,1],[2,1,1,'2026-10-03',100,1],[3,2,2,'2026-10-03',999,1],[4,1,1,'2026-10-03',20,2],[5,1,1,'2026-10-10',800,1]] as [$id,$company,$worker,$date,$wt,$item]) {
            DB::table('jobwork_issues')->insert(['id'=>$id,'company_id'=>$company,'job_worker_id'=>$worker,'jobwork_date'=>$date,'voucher_no'=>'JI-'.$id]);
            DB::table('jobwork_issue_items')->insert(['id'=>$id,'jobwork_issue_id'=>$id,'item_id'=>$item,'net_wt'=>$wt,'fine_wt'=>$wt*.935]);
        }
        foreach ([[1,200],[2,290]] as [$id,$wt]) {
            DB::table('jobwork_receives')->insert(['id'=>$id,'company_id'=>1,'jobwork_issue_id'=>1,'job_worker_id'=>null,'receive_date'=>'2026-10-02','receive_no'=>'JR-'.$id]);
            DB::table('jobwork_receive_items')->insert(['id'=>$id,'jobwork_receive_id'=>$id,'jobwork_issue_item_id'=>1,'item_id'=>null,'receive_net_wt'=>$wt,'receive_fine_wt'=>$wt*.935]);
        }
        $ledger = app(WorkerMetalLedger::class)->report(1, new Request(['from_date'=>'2026-10-01','to_date'=>'2026-10-07']));
        $this->assertCount(2, $ledger['groups']);
        $silver = $ledger['groups'][0];
        $this->assertEquals(500, $silver['opening']);
        $this->assertEquals(100, $silver['debit']);
        $this->assertEquals(490, $silver['credit']);
        $this->assertEquals(110, $silver['closing']);
        $this->assertEquals(10, $silver['rows'][1]['balance']);
        $this->assertArrayNotHasKey('fine_closing', $silver);
        $this->assertArrayNotHasKey('fine_debit', $silver['rows'][0]);
        $this->assertEquals(20, $ledger['groups'][1]['closing']);
        $html = view('company.reports.pdf.worker_metal_ledger', ['ledger'=>$ledger,'company'=>(object)['name'=>'Demo Company']])->render();
        $this->assertStringContainsString('110.000 Dr', $html);
        $this->assertStringNotContainsString('Other Company', $html);
        $this->assertStringContainsString('Opening balance', $html);
        $this->assertStringNotContainsString('Fine Debit', $html);
        $all = app(WorkerMetalLedger::class)->report(1, new Request(['to_date'=>'2026-10-07']));
        $this->assertEquals(0, $all['groups'][0]['opening']);
        $this->assertEquals(600, $all['groups'][0]['debit']);
        $foreign = app(WorkerMetalLedger::class)->report(1, new Request(['worker_id'=>2]));
        $this->assertSame([], $foreign['groups']);
        $daily = app(WorkerMetalLedger::class)->report(1, new Request(['from_date'=>'2026-10-02','to_date'=>'2026-10-02','format'=>'daily']));
        $this->assertSame('daily', $daily['filters']['format']);
        $this->assertCount(1, $daily['groups'][0]['rows']);
        $this->assertEquals(500, $daily['groups'][0]['opening']);
        $this->assertEquals(490, $daily['groups'][0]['rows'][0]['credit']);
        $this->assertEquals(10, $daily['groups'][0]['rows'][0]['balance']);
        $this->assertEquals(10, $daily['groups'][0]['closing']);
        $dailyHtml = view('company.reports.pdf.worker_metal_ledger', ['ledger'=>$daily,'company'=>(object)['name'=>'Demo']])->render();
        $this->assertStringContainsString('Date-wise Summary', $dailyHtml);
        $this->assertStringContainsString('02-10-2026', $dailyHtml);
        $this->assertStringNotContainsString('Voucher / Reference', $dailyHtml);
        $company = new \App\Models\Company;
        $company->id = 1;
        $controller = app(\App\Http\Controllers\Company\ReportController::class);
        $method = new \ReflectionMethod($controller, 'workerMetalLedger');
        $method->setAccessible(true);
        $request = new Request(['worker_id'=>\Illuminate\Support\Facades\Crypt::encryptString('1'), 'format'=>'daily']);
        $web = $method->invoke($controller, $company, $request);
        $this->assertEquals(1, $web['filters']['worker_id']);
        $this->assertFalse(is_numeric($request->input('worker_id')));
        foreach (['1', 'invalid-token', \Illuminate\Support\Facades\Crypt::encryptString('2')] as $invalid) {
            try {
                $method->invoke($controller, $company, new Request(['worker_id'=>$invalid]));
                $this->fail('Invalid worker selection was accepted');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(422, $e->getStatusCode());
            }
        }
        DB::statement('CREATE TABLE companies (id integer primary key)');
        DB::table('companies')->insert(['id'=>1]);
        DB::statement('CREATE TABLE users (id integer primary key)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer)');
        DB::table('customers')->insert(['id'=>10,'company_id'=>1]);
        DB::statement('ALTER TABLE job_workers ADD COLUMN person_id integer');
        DB::table('job_workers')->where('id',1)->update(['person_id'=>10]);
        (require database_path('migrations/2026_05_30_120000_create_customer_advance_ledgers_table.php'))->up();
        $person = \App\Models\Customer::findOrFail(10);
        $openings = new \App\Services\PersonOpeningBalance;
        $payload = ['date'=>'2026-09-01','cash'=>50000,'cash_side'=>'cr','silver'=>100,'silver_side'=>'dr','gold'=>0,'gold_side'=>'dr','other'=>0,'other_side'=>'dr'];
        DB::transaction(fn()=> $openings->save($person,$payload,null));
        DB::transaction(fn()=> $openings->save($person,$payload,null));
        $this->assertSame(2, DB::table('customer_advance_ledgers')->count());
        $this->assertEquals(50000, $openings->read($person)['cash']);
        $this->assertSame('cr', $openings->read($person)['cash_side']);
        $withOpening = app(WorkerMetalLedger::class)->report(1,new Request(['from_date'=>'2026-10-01','to_date'=>'2026-10-07']));
        $this->assertEquals(600, $withOpening['groups'][0]['opening']);
        $this->assertEquals(210, $withOpening['groups'][0]['closing']);
        $this->assertEquals(50000, $withOpening['cash_openings'][0]->credit);
        $payload['silver']=25; $payload['silver_side']='cr';
        DB::transaction(fn()=> $openings->save($person,$payload,null));
        $this->assertEquals(25, $openings->read($person)['silver']);
        $changed = app(WorkerMetalLedger::class)->report(1,new Request(['to_date'=>'2026-10-07']));
        $this->assertEquals(85,$changed['groups'][0]['closing']);
        DB::transaction(fn()=> $openings->save($person,null,null));
        $this->assertSame(2, DB::table('customer_advance_ledgers')->count());
    }

    public function test_opening_only_and_excess_receipt_balances(): void
    {
        $entry = ['id'=>1,'worker_id'=>1,'worker_name'=>'Worker','metal'=>'Silver','date'=>'2026-09-01','debit'=>500,'credit'=>0,'fine_debit'=>467.5,'fine_credit'=>0];
        $service = new WorkerMetalLedger;
        $groups = $service->balances([$entry], '2026-10-01');
        $this->assertEquals(500, $groups[0]['closing']);
        $this->assertSame([], $groups[0]['rows']);
        $entry['date']='2026-10-02'; $entry['debit']=0; $entry['credit']=510;
        $groups = $service->balances([$entry], null);
        $this->assertEquals(-510, $groups[0]['closing']);
    }

    public function test_metal_case_variants_share_opening_and_running_balance(): void
    {
        $base = ['worker_id'=>1, 'worker_name'=>'Worker', 'date'=>'2026-10-02', 'debit'=>0, 'credit'=>0];
        $entries = [
            array_merge($base, ['metal'=>'silver', 'date'=>'2026-09-30', 'debit'=>500]),
            array_merge($base, ['metal'=>'Silver', 'credit'=>490]),
            array_merge($base, ['metal'=>' SILVER ', 'debit'=>20]),
            array_merge($base, ['metal'=>'Gold', 'debit'=>5]),
            array_merge($base, ['metal'=>'silver', 'worker_id'=>2, 'debit'=>7]),
        ];
        $groups = (new WorkerMetalLedger)->balances($entries, '2026-10-01');
        $this->assertCount(3, $groups);
        $this->assertSame('Silver', $groups[0]['metal']);
        $this->assertEquals(500, $groups[0]['opening']);
        $this->assertEquals(490, $groups[0]['credit']);
        $this->assertEquals(20, $groups[0]['debit']);
        $this->assertEquals(30, $groups[0]['closing']);
        $this->assertEquals(10, $groups[0]['rows'][0]['balance']);
        $this->assertEquals(30, $groups[0]['rows'][1]['balance']);
        $this->assertSame('Silver', $groups[0]['rows'][1]['metal']);
        $this->assertSame('Gold', $groups[1]['metal']);
        $this->assertEquals(7, $groups[2]['closing']);
    }
}
