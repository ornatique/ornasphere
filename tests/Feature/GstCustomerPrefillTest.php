<?php
namespace Tests\Feature;

use App\Http\Controllers\Company\CustomerController;
use App\Http\Controllers\Company\GstinLookupController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GstCustomerPrefillTest extends TestCase
{
    public function test_add_customer_opens_prefilled_form_with_customer_category_without_saving(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, slug text)');
        DB::statement('CREATE TABLE customers (id integer primary key, company_id integer, gst_no text)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text, is_system_default integer, created_at datetime, updated_at datetime)');
        DB::table('companies')->insert(['id'=>1,'slug'=>'test']);
        config(['services.gstin.key'=>'fake']);
        Http::fake(['*'=>Http::response(['success'=>true,'data'=>[
            'gstin'=>'24GDTPS5199L1ZB','legal_name'=>'Legal Name','trade_name'=>'ORNATIQUE SILVER',
            'address'=>'Test address','pincode'=>'380004','status'=>'Active',
            'business_constitution'=>'Proprietorship','registration_date'=>'2026-02-25',
            'address_details'=>['city'=>'Ahmedabad','locality'=>'Test area'],
        ]])]);
        $user = new User; $user->forceFill(['id'=>1,'company_id'=>1]);
        $session = app('session.store'); $session->start();
        $search = Request::create('/','POST',['gstin'=>'24GDTPS5199L1ZB']);
        $search->setUserResolver(fn () => $user); $search->setLaravelSession($session);
        $response = (new GstinLookupController)->search($search,'test')->getData(true);
        parse_str(parse_url($response['create_url'], PHP_URL_QUERY), $query);
        $create = Request::create('/','GET',$query); $create->setLaravelSession($session);
        $view = (new CustomerController)->create($create,'test')->getData();
        $data = $view['gstPrefill'];
        $this->assertSame('ORNATIQUE SILVER',$data['name']);
        $this->assertSame('GDTPS5199L',$data['pan_no']);
        $this->assertSame('Test address',$data['address']);
        $this->assertSame('Ahmedabad',$data['city']);
        $this->assertSame('380004',$data['pincode']);
        $this->assertSame('Customer',DB::table('category_people')->where('id',$data['category_person_id'])->value('category_name'));
        $this->assertStringContainsString('Registration date: 2026-02-25',$data['remarks']);
        $this->assertSame(0,DB::table('customers')->count());
        $apiUser = \Mockery::mock(User::class)->makePartial();
        $apiUser->forceFill(['id'=>1,'company_id'=>1]);
        $apiUser->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
        $apiUser->shouldReceive('hasAnyPermission')->with(['gst-search-view'])->andReturn(true);
        $apiUser->shouldReceive('hasAnyPermission')->with(['person-create','customer-create'])->andReturn(false);
        $apiRequest = Request::create('/api/gst-search','POST',['gstin'=>'24GDTPS5199L1ZB']);
        $apiRequest->setUserResolver(fn () => $apiUser);
        $api = new \App\Http\Controllers\Api\GstinApiController;
        $apiData = $api->lookup($apiRequest)->getData(true)['data'];
        $this->assertSame('Customer',$apiData['category_name']);
        $this->assertSame($data['name'],$apiData['customer_form']['name']);
        $this->assertSame($data['category_person_id'],$apiData['customer_form']['category_person_id']);
        $this->assertFalse($apiData['can_add_customer']);
        try { $api->storeCustomer($apiRequest); $this->fail('View-only user could save customer'); }
        catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $create->query->set('gst_prefill','wrong-token');
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CustomerController)->create($create,'test');
    }
}
