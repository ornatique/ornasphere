<?php
namespace Tests\Feature;

use App\Http\Controllers\Api\CustomerAdvanceApiController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApiItemReceiptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        foreach ([
            'companies'=>'id integer primary key, name text, voucher_prefix text',
            'users'=>'id integer primary key',
            'category_people'=>'id integer primary key autoincrement, company_id integer, category_name text, is_system_default integer, created_at text, updated_at text',
            'customers'=>'id integer primary key, company_id integer, category_person_id integer, name text, is_active integer',
            'items'=>'id integer primary key, company_id integer, item_name text, metal text',
            'item_sets'=>'id integer primary key',
            'company_voucher_counters'=>'company_id integer, module text, last_number integer',
        ] as $table=>$columns) DB::statement("CREATE TABLE $table ($columns)");
        (require database_path('migrations/2026_05_30_120000_create_customer_advance_ledgers_table.php'))->up();
        (require database_path('migrations/2026_08_14_000001_create_customer_advance_vouchers_tables.php'))->up();
        DB::table('companies')->insert(['id'=>1,'name'=>'Test']);
        DB::table('users')->insert(['id'=>1]);
        DB::table('category_people')->insert(['id'=>1,'company_id'=>1,'category_name'=>'Customer']);
        DB::table('customers')->insert(['id'=>180,'company_id'=>1,'category_person_id'=>1,'name'=>'Customer','is_active'=>1]);
        DB::table('items')->insert(['id'=>331,'company_id'=>1,'item_name'=>'Patru','metal'=>'Silver']);
    }

    private function send(array $body)
    {
        $r = Request::create('/api/sales/advance-ledger/entries','POST',$body);
        $user = new \App\Models\User;
        $user->forceFill(['id'=>1,'company_id'=>1]);
        $user->setRelation('company', \App\Models\Company::findOrFail(1));
        $r->setUserResolver(fn() => $user);
        return app(CustomerAdvanceApiController::class)->store($r);
    }

    private function payload(): array
    {
        return ['entry_date'=>'2026-10-12','customer_id'=>180,'remarks'=>'','items'=>[
            ['product_id'=>331,'gross_weight'=>58.86,'other_weight'=>0,'net_weight'=>58.86,
            'purity'=>93.5,'waste_percent'=>0,'net_purity'=>93.5,'fine_weight'=>55.034,'metal_rate'=>0,'labour_rate'=>50]
        ]];
    }

    public function test_items_without_cash_create_stock_and_fine_credit(): void
    {
        $response = $this->send($this->payload());
        $this->assertEquals(200,$response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('item_receive',$data['voucher']['entry_type']);
        $this->assertEquals(58.86,$data['voucher']['items'][0]['net_weight']);
        $this->assertEquals(55.034,$data['balance']['metal_balance']['silver']);
        $this->assertEquals(2943,$data['voucher']['items'][0]['labour_amount']);
        $this->assertEquals(0,$data['balance']['cash_balance']);
        $this->assertEquals(1,DB::table('customer_advance_voucher_items')->count());
    }

    public function test_foreign_product_is_rejected_without_writes(): void
    {
        DB::table('items')->where('id',331)->update(['company_id'=>2]);
        $this->assertEquals(422,$this->send($this->payload())->getStatusCode());
        $this->assertEquals(0,DB::table('customer_advance_vouchers')->count());
    }

    public function test_invalid_weights_and_empty_items_are_rejected(): void
    {
        $body=$this->payload(); $body['items'][0]['other_weight']=60;
        $this->assertEquals(422,$this->send($body)->getStatusCode());
        $body['items']=[];
        $this->assertEquals(422,$this->send($body)->getStatusCode());
        $this->assertEquals(0,DB::table('customer_advance_vouchers')->count());
    }

    public function test_cash_only_request_still_requires_positive_amount(): void
    {
        $body=['entry_date'=>'2026-10-12','customer_id'=>180];
        $this->assertEquals(422,$this->send($body)->getStatusCode());
        $body['amount']=500;
        $response=$this->send($body);
        $this->assertEquals(200,$response->getStatusCode());
        $this->assertEquals(500,$response->getData(true)['balance']['cash_balance']);
        $this->assertEquals(0,DB::table('customer_advance_voucher_items')->count());
    }
}
