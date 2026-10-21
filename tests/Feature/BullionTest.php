<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use App\Models\{Company,User};
use App\Http\Controllers\Company\BullionController;

class BullionTest extends TestCase
{
    public function test_deposit_conversion_and_return_share_one_ledger_and_voucher(): void
    {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-15 14:35:27', config('app.timezone')));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, name text, slug text, voucher_prefix text)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, category_person_id integer)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text)');
        DB::table('category_people')->insert([['id'=>1,'company_id'=>1,'category_name'=>'Bullion'],['id'=>2,'company_id'=>1,'category_name'=>'Customer']]);
        DB::statement('CREATE TABLE users (id integer primary key)');
        DB::statement('CREATE TABLE company_voucher_counters (company_id integer, module text, last_number integer)');
        (require database_path('migrations/2026_05_30_120000_create_customer_advance_ledgers_table.php'))->up();
        (require database_path('migrations/2026_08_14_000001_create_customer_advance_vouchers_tables.php'))->up();
        DB::table('companies')->insert(['id'=>1,'name'=>'Test','slug'=>'test']);
        DB::table('customers')->insert([['id'=>1,'company_id'=>1,'category_person_id'=>1],['id'=>2,'company_id'=>1,'category_person_id'=>2]]);
        DB::table('users')->insert(['id'=>1]);
        $user=\Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>1,'company_id'=>1]);
        $user->setRelation('company',Company::find(1));
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        foreach ([['entry_type'=>'receive_amount','amount'=>10000],['entry_type'=>'convert_to_metal','amount'=>10000,'rate'=>100,'metal_type'=>'gold'],['entry_type'=>'return_metal','net_weight'=>20,'purity'=>100,'metal_type'=>'gold']] as $entry) {
            $request=Request::create('/','POST',$entry+['customer_id'=>1,'entry_date'=>'2026-10-14','rate_confirmed'=>1]);
            $request->setUserResolver(fn()=>$user);
            (new BullionController)->store($request,'test');
        }
        $this->assertEquals(3,DB::table('customer_advance_vouchers')->count());
        $this->assertEquals(3,DB::table('customer_advance_ledgers')->count());
        $saved = \App\Models\CustomerAdvanceLedger::firstOrFail();
        $this->assertSame('2026-10-14', $saved->entry_date->format('Y-m-d'));
        $this->assertSame('2026-10-15 14:35:27', $saved->created_at->format('Y-m-d H:i:s'));
        $this->assertEquals(0,DB::table('customer_advance_ledgers')->sum(DB::raw('cash_in-cash_out')));
        $this->assertEquals(80,DB::table('customer_advance_ledgers')->sum(DB::raw('metal_in-metal_out')));
        $request=Request::create('/','POST',['entry_type'=>'return_metal','net_weight'=>81,'purity'=>100,'metal_type'=>'gold','customer_id'=>1,'entry_date'=>'2026-10-14','rate_confirmed'=>1]);
        $request->setUserResolver(fn()=>$user);
        try { (new BullionController)->store($request,'test'); $this->fail('Over-return accepted'); }
        catch (\Illuminate\Validation\ValidationException $e) { $this->assertEquals(3,DB::table('customer_advance_ledgers')->count()); }
        $request->merge(['customer_id'=>2,'entry_type'=>'receive_amount','amount'=>100]);
        try { (new BullionController)->store($request,'test'); $this->fail('Customer accepted in supplier bullion'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertEquals(3,DB::table('customer_advance_ledgers')->count()); }
        // Both conversion directions may exceed the customer's available balance.
        foreach ([
            ['entry_type'=>'convert_to_metal','amount'=>50000,'rate'=>100,'metal_type'=>'silver'],
            ['entry_type'=>'convert_to_rupees','net_weight'=>100,'purity'=>100,'rate'=>100,'metal_type'=>'gold'],
            ['entry_type'=>'convert_to_rupees','net_weight'=>600,'purity'=>100,'rate'=>100,'metal_type'=>'silver'],
        ] as $conversion) {
            $request = Request::create('/', 'POST', $conversion + ['customer_id'=>1,'entry_date'=>'2026-10-15','rate_confirmed'=>1]);
            $request->setUserResolver(fn()=>$user);
            (new BullionController)->store($request, 'test');
            if ($conversion['entry_type'] === 'convert_to_metal') {
                $this->assertEquals(-50000, DB::table('customer_advance_ledgers')->sum(DB::raw('cash_in-cash_out')));
            }
        }
        $this->assertEquals(-20, DB::table('customer_advance_ledgers')->where('metal_type','gold')->sum(DB::raw('metal_in-metal_out')));
        $this->assertEquals(-100, DB::table('customer_advance_ledgers')->where('metal_type','silver')->sum(DB::raw('metal_in-metal_out')));
        $this->assertEquals(20000, DB::table('customer_advance_ledgers')->sum(DB::raw('cash_in-cash_out')));
        $this->assertEquals(6, DB::table('customer_advance_vouchers')->count());
        $api = new \App\Http\Controllers\Api\BullionApiController;
        $request = Request::create('/', 'POST', ['person_id'=>1,'entry_type'=>'convert_to_rupees','metal_type'=>'silver','net_weight'=>500,'purity'=>100,'rate'=>216.96,'entry_date'=>'2026-10-15','rate_confirmed'=>true]);
        $request->setUserResolver(fn()=>$user);
        $preview = $api->preview($request)->getData(true)['data'];
        $this->assertEquals(108480, $preview['amount']);
        $this->assertEquals(6, DB::table('customer_advance_vouchers')->count());
        $saved = $api->store($request);
        $this->assertEquals(201, $saved->getStatusCode());
        $this->assertEquals($preview['balance_after'], $saved->getData(true)['data']['balance']);
        $this->assertEquals(7, $api->entries($request)->getData(true)['data']['total']);
        DB::statement('ALTER TABLE customers ADD COLUMN name text');
        DB::table('customers')->where('id',1)->update(['name'=>'Bullion test']);
        $pdfRequest = Request::create('/', 'GET', ['person_id'=>1]);
        $pdfRequest->setUserResolver(fn()=>$user);
        $pdf = $api->pdf($pdfRequest);
        $this->assertSame('application/pdf', $pdf->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-', $pdf->getContent());
        $request->merge(['person_id'=>2]);
        try { $api->balance($request); $this->fail('Customer category accepted'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertEquals(7, DB::table('customer_advance_vouchers')->count()); }
    }
}
