<?php
namespace Tests\Feature;

use App\Http\Controllers\Company\OrderManagementController;
use App\Models\{ManagedOrder, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Schema};
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrderManagementTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('companies', function (Blueprint $t) { $t->id(); $t->string('name'); $t->string('slug'); });
        Schema::create('users', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('email'); });
        Schema::create('category_people', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('category_name'); $t->boolean('is_system_default')->default(false); $t->timestamps(); });
        Schema::create('customers', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('category_person_id')->nullable();
            $t->string('name'); $t->string('email')->nullable(); $t->string('mobile_no')->nullable(); $t->string('city')->nullable(); $t->string('pincode')->nullable(); $t->text('address')->nullable(); $t->boolean('is_active')->default(true); $t->timestamps();
        });
        Schema::create('items', function (Blueprint $t) { $t->id(); $t->unsignedBigInteger('company_id'); $t->string('item_name'); });
        Schema::create('item_sets', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->unsignedBigInteger('item_id'); $t->string('HUID')->nullable(); $t->string('qr_code')->nullable(); $t->string('barcode')->nullable();
            $t->boolean('is_final')->default(true); $t->integer('available_quantity')->default(3); $t->decimal('gross_weight', 15, 3)->default(5); $t->decimal('net_weight', 15, 3)->default(4);
        });
        (require database_path('migrations/2026_11_25_120000_create_managed_orders_tables.php'))->up();
        (require database_path('migrations/2026_11_25_130000_add_pricing_to_managed_order_items.php'))->up();
        (require database_path('migrations/2026_11_25_140000_add_price_mode_to_managed_orders.php'))->up();
        DB::table('companies')->insert([['id'=>1,'name'=>'One','slug'=>'one'], ['id'=>2,'name'=>'Two','slug'=>'two']]);
        DB::table('customers')->insert([['id'=>1,'company_id'=>1,'name'=>'Alice'], ['id'=>2,'company_id'=>2,'name'=>'Other customer']]);
        DB::table('items')->insert([['id'=>1,'company_id'=>1,'item_name'=>'Ring'], ['id'=>2,'company_id'=>2,'item_name'=>'Other Ring']]);
        DB::table('item_sets')->insert([['id'=>1,'company_id'=>1,'item_id'=>1,'HUID'=>'ABC123','qr_code'=>'QR-ONE'], ['id'=>2,'company_id'=>2,'item_id'=>2,'HUID'=>'ABC123','qr_code'=>'QR-TWO']]);
    }

    private function request(array $data = [], string $method = 'POST'): Request
    {
        $r = Request::create('/', $method, $data);
        $r->setUserResolver(fn () => new User(['company_id'=>1]));
        return $r;
    }

    private function data(array $replace = []): array
    {
        return array_replace(['customer_id'=>1,'order_date'=>'2026-11-25','remarks'=>'Keep safe','items'=>[['item_set_id'=>1,'quantity'=>2]]], $replace);
    }

    public function test_existing_customer_order_saves_server_snapshots_without_changing_stock(): void
    {
        $before = DB::table('item_sets')->get()->toJson();
        (new OrderManagementController)->store($this->request($this->data(['items'=>[['item_set_id'=>1,'quantity'=>2,'net_weight'=>999,'total_amount'=>999]]])), 'one');
        $order = ManagedOrder::with('items')->firstOrFail();
        $this->assertSame(1, (int) $order->customer_id);
        $this->assertEquals(5, $order->items[0]->gross_weight);
        $this->assertEquals(4, $order->items[0]->net_weight);
        $this->assertEquals(0, $order->items[0]->total_amount);
        $this->assertSame('ABC123', $order->items[0]->huid);
        $this->assertSame($before, DB::table('item_sets')->get()->toJson());
        $this->assertSame(0, ManagedOrder::where('company_id', 2)->count());
    }

    public function test_other_creates_customer_in_same_company_and_selects_it(): void
    {
        (new OrderManagementController)->store($this->request($this->data(['customer_id'=>'other','new_customer'=>['name'=>' New Customer ','email'=>'new@example.test','mobile_no'=>'9876543210']])), 'one');
        $customer = DB::table('customers')->where('email','new@example.test')->first();
        $this->assertSame('New Customer', $customer->name);
        $this->assertSame(1, (int) $customer->company_id);
        $this->assertSame($customer->id, ManagedOrder::first()->customer_id);
        $this->assertSame('Customer', DB::table('category_people')->where('id',$customer->category_person_id)->value('category_name'));
    }

    public function test_invalid_stock_does_not_leave_a_new_customer_or_partial_order(): void
    {
        try {
            (new OrderManagementController)->store($this->request($this->data(['customer_id'=>'other','new_customer'=>['name'=>'Not saved'],'items'=>[['item_set_id'=>2,'quantity'=>1]]])), 'one');
            $this->fail('Expected validation failure');
        } catch (ValidationException $e) { $this->assertArrayHasKey('items.0.quantity', $e->errors()); }
        $this->assertSame(2, DB::table('customers')->count());
        $this->assertSame(0, ManagedOrder::count());
    }

    public function test_cross_company_customer_and_duplicate_items_are_rejected(): void
    {
        foreach ([$this->data(['customer_id'=>2]), $this->data(['items'=>[['item_set_id'=>1,'quantity'=>1],['item_set_id'=>1,'quantity'=>1]]])] as $data) {
            try { (new OrderManagementController)->store($this->request($data), 'one'); $this->fail('Expected validation failure'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertSame(0, ManagedOrder::count());
    }

    public function test_search_only_returns_available_labels_owned_by_current_company(): void
    {
        $controller = new OrderManagementController;
        $json = $controller->search($this->request(['keyword'=>'ABC123'], 'GET'), 'one')->getData(true);
        $this->assertCount(1, $json); $this->assertSame(1, $json[0]['item_set_id']);
        DB::table('item_sets')->where('id',1)->update(['available_quantity'=>0]);
        $this->assertSame([], $controller->search($this->request(['keyword'=>'ABC123'], 'GET'), 'one')->getData(true));
    }

    public function test_selected_labels_are_excluded_from_subsequent_searches(): void
    {
        $controller = new OrderManagementController;
        $this->assertCount(1, $controller->search($this->request(['keyword'=>'ABC123'], 'GET'), 'one')->getData(true));
        $this->assertSame([], $controller->search($this->request(['keyword'=>'ABC123', 'exclude'=>[1]], 'GET'), 'one')->getData(true));
        // Removing a row means its ID is no longer excluded on the next request.
        $this->assertCount(1, $controller->search($this->request(['keyword'=>'ABC123', 'exclude'=>[]], 'GET'), 'one')->getData(true));
    }

    public function test_edit_replaces_lines_and_keeps_other_company_data_unchanged(): void
    {
        $controller = new OrderManagementController;
        $controller->store($this->request($this->data()), 'one');
        $order = ManagedOrder::first();
        $controller->store($this->request($this->data(['remarks'=>'Updated','items'=>[['item_set_id'=>1,'quantity'=>1]]]), 'PUT'), 'one', $order->id);
        $this->assertSame(1, ManagedOrder::count());
        $this->assertSame('Updated', $order->fresh()->remarks);
        $this->assertSame(1, $order->items()->first()->quantity);
        $this->assertSame('Other customer', DB::table('customers')->where('id',2)->value('name'));
    }

    public function test_different_company_url_is_forbidden(): void
    {
        $this->expectException(HttpException::class);
        (new OrderManagementController)->search($this->request([], 'GET'), 'two');
    }

    public function test_invalid_quantity_and_empty_items_are_rejected(): void
    {
        foreach ([[], [['item_set_id'=>1, 'quantity'=>0]], [['item_set_id'=>1, 'quantity'=>1000001]]] as $items) {
            try { (new OrderManagementController)->store($this->request($this->data(['items'=>$items])), 'one'); $this->fail('Expected validation failure'); }
            catch (ValidationException $e) { $this->assertNotEmpty($e->errors()); }
        }
        $this->assertSame(0, ManagedOrder::count());
    }

    public function test_duplicate_email_rejects_customer_and_order_atomically(): void
    {
        DB::table('users')->insert(['company_id'=>1, 'email'=>'Same@Example.test']);
        try {
            (new OrderManagementController)->store($this->request($this->data(['customer_id'=>'other', 'new_customer'=>['name'=>'Duplicate','email'=>'same@example.test']])), 'one');
            $this->fail('Expected duplicate email rejection');
        } catch (ValidationException $e) { $this->assertArrayHasKey('new_customer.email', $e->errors()); }
        $this->assertSame(2, DB::table('customers')->count());
        $this->assertSame(0, ManagedOrder::count());
    }

    public function test_cannot_edit_another_company_order(): void
    {
        $other = ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        try { (new OrderManagementController)->store($this->request($this->data()), 'one', $other->id); $this->fail('Expected not found'); }
        catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) { $this->assertSame(ManagedOrder::class, $e->getModel()); }
        $this->assertSame(2, (int) $other->fresh()->customer_id);
    }

    public function test_form_and_list_render_with_selected_items_and_other_customer_option(): void
    {
        // Isolate page rendering from unrelated dashboard widgets and their tables.
        $dir = storage_path('framework/testing/order-view-layout');
        \Illuminate\Support\Facades\File::ensureDirectoryExists($dir.'/company_layout');
        file_put_contents($dir.'/company_layout/admin.blade.php', "@yield('content') @stack('scripts')");
        app('view')->getFinder()->prependLocation($dir);
        app('view')->share('errors', new \Illuminate\Support\ViewErrorBag);
        config(['view.compiled'=>storage_path('framework/views')]);
        config(['services.google.maps_api_key'=>'test-key']);
        $controller = new OrderManagementController;
        $controller->store($this->request($this->data()), 'one');
        $html = $controller->edit($this->request([], 'GET'), 'one', ManagedOrder::first()->id)->render();
        $this->assertStringContainsString('Other — Create new customer', $html);
        $this->assertStringContainsString('Scan / Search (HUID / QR)', $html);
        $this->assertStringContainsString('ABC123', $html);
        $this->assertStringContainsString('document.getElementById("customer-city")', $html);
        $this->assertStringContainsString('document.getElementById("customer-address")', $html);
        $this->assertStringContainsString('initCustomerAddressAutocomplete', $html);
        $this->assertStringContainsString('Net Purity', $html);
        $this->assertStringContainsString('OM-000001', $controller->index($this->request([], 'GET'), 'one')->render());
        config(['app.order_management_asset_prefix'=>'']);
        $this->assertStringContainsString('/js/order-management.js?v=6', $controller->edit($this->request([], 'GET'), 'one', ManagedOrder::first()->id)->render());
        config(['app.order_management_asset_prefix'=>'/public/']);
        $this->assertStringContainsString('/public/js/order-management.js?v=6', $controller->edit($this->request([], 'GET'), 'one', ManagedOrder::first()->id)->render());
        $this->assertStringContainsString('/public/js/order-management-list.js?v=2', $controller->index($this->request([], 'GET'), 'one')->render());
    }

    public function test_new_tables_are_covered_by_archive_policy_and_routes_are_permission_mapped(): void
    {
        $this->assertContains('managed_orders', config('company_archive.business'));
        $this->assertContains('managed_order_items', config('company_archive.business'));
        $this->assertSame('managed_orders', config('company_archive.references.managed_order_id'));
        $this->assertSame('order-management', (new \App\Http\Middleware\CompanyRoutePermission)->moduleFromRouteName('company.order-management.store'));
        $this->assertSame('sales', \App\Services\CompanyModules::permissionModule('order-management'));
        $this->assertTrue(app('router')->has('company.order-management.update'));
    }

    public function test_approval_style_fields_are_calculated_saved_and_reloaded(): void
    {
        $controller = new OrderManagementController;
        $line = ['item_set_id'=>1,'quantity'=>2,'gross_weight'=>50,'other_weight'=>1,'purity'=>94,'waste_percent'=>2,
            'metal_rate'=>100,'labour_rate'=>10,'other_amount'=>25,'remarks'=>'Test row','net_weight'=>900,'metal_amount'=>900,'total_amount'=>1];
        $controller->store($this->request($this->data(['items'=>[$line]])), 'one');
        $order = ManagedOrder::with('items')->firstOrFail(); $saved = $order->items[0];
        $this->assertEquals(49, $saved->net_weight);
        $this->assertEquals(96, $saved->net_purity);
        $this->assertEquals(47.04, $saved->total_fine_weight);
        $this->assertEquals(4900, $saved->metal_amount);
        $this->assertEquals(10830, $saved->total_amount);
        $rows = $controller->edit($this->request([], 'GET'), 'one', $order->id)->getData()['rows'];
        $this->assertEquals(50, $rows[0]['gross_weight']);
        $this->assertEquals(94, $rows[0]['purity']);
        $this->assertSame('Test row', $rows[0]['remarks']);
        $this->assertEquals(5, DB::table('item_sets')->where('id',1)->value('gross_weight'));
    }

    public function test_other_weight_cannot_exceed_gross_weight(): void
    {
        $this->expectException(ValidationException::class);
        (new OrderManagementController)->store($this->request($this->data(['items'=>[['item_set_id'=>1,'quantity'=>1,'gross_weight'=>5,'other_weight'=>6]]])), 'one');
    }

    public function test_fixed_sale_price_overrides_submitted_amount_and_cannot_be_spoofed(): void
    {
        $result = \App\Services\OrderManagementPricing::calculate(['quantity'=>2, 'sale_price'=>1, 'metal_rate'=>100],
            ['gross_weight'=>5, 'other_weight'=>1, 'purity'=>94, 'sale_price'=>750]);
        $this->assertEquals(1500, $result['total_amount']);
        $this->assertEquals(0, $result['metal_amount']);
        $this->assertEquals(0, $result['total_fine_weight']);
    }

    public function test_order_pdf_uses_saved_values_and_does_not_modify_records(): void
    {
        DB::table('customers')->where('id',1)->update(['mobile_no'=>'9876543210', 'email'=>'private@example.test', 'address'=>'Private Street', 'city'=>'Private City', 'pincode'=>'380001']);
        $controller = new OrderManagementController;
        $controller->store($this->request($this->data(['items'=>[['item_set_id'=>1,'quantity'=>2,'purity'=>94,'metal_rate'=>100]]])), 'one');
        $order = ManagedOrder::with('items')->firstOrFail();
        $before = $order->toJson();
        $html = view('company.order_management.pdf', ['company'=>\App\Models\Company::findOrFail(1), 'order'=>$order->fresh(['items','customer'])])->render();
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('9876543210', $html);
        $this->assertStringNotContainsString('Wholesale Price', $html);
        foreach (['private@example.test','Private Street','Private City','380001'] as $privateDetail) $this->assertStringNotContainsString($privateDetail, $html);
        $response = $controller->pdf($this->request([], 'GET'), 'one', $order->id);
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('inline', $response->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertSame($before, $order->fresh(['items'])->toJson());
        file_put_contents(storage_path('order-pdf-preview.pdf'), $response->getContent());
    }

    public function test_pdf_rejects_an_order_owned_by_another_company(): void
    {
        $order = ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        (new OrderManagementController)->pdf($this->request([], 'GET'), 'one', $order->id);
    }

    public function test_list_customer_filter_combines_with_search_and_remains_company_scoped(): void
    {
        DB::table('customers')->insert(['id'=>3,'company_id'=>1,'name'=>'Bob']);
        ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-25']);
        ManagedOrder::create(['company_id'=>1,'customer_id'=>3,'order_date'=>'2026-11-25']);
        ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        $controller = new OrderManagementController;
        $data = $controller->index($this->request(['customer_id'=>1,'search'=>'Alice'], 'GET'), 'one')->getData();
        $this->assertSame(1, $data['orders']->total());
        $this->assertSame([1,3], $data['customers']->pluck('id')->all());
        $this->assertSame(0, $controller->index($this->request(['customer_id'=>1,'search'=>'Bob'], 'GET'), 'one')->getData()['orders']->total());
    }

    public function test_list_rejects_a_customer_from_another_company(): void
    {
        $this->expectException(ValidationException::class);
        (new OrderManagementController)->index($this->request(['customer_id'=>2], 'GET'), 'one');
    }

    public function test_live_list_search_returns_only_the_table_html(): void
    {
        ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-25']);
        $request = $this->request(['search'=>'Alice'], 'GET');
        $request->headers->set('Accept','application/json');
        $response = (new OrderManagementController)->index($request, 'one');
        $html = $response->getData(true)['html'];
        $this->assertStringContainsString('Alice', $html);
        $this->assertStringContainsString('OM-000001', $html);
        $this->assertStringNotContainsString('order-list-filters', $html);
    }

    public function test_order_date_range_is_inclusive_and_supports_one_sided_filters(): void
    {
        foreach (['2026-11-24','2026-11-25','2026-11-26'] as $date) ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>$date]);
        ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        $controller = new OrderManagementController;
        $this->assertSame(1, $controller->index($this->request(['from_date'=>'2026-11-25','to_date'=>'2026-11-25'], 'GET'), 'one')->getData()['orders']->total());
        $this->assertSame(2, $controller->index($this->request(['from_date'=>'2026-11-25'], 'GET'), 'one')->getData()['orders']->total());
        $this->assertSame(2, $controller->index($this->request(['to_date'=>'2026-11-25'], 'GET'), 'one')->getData()['orders']->total());
    }

    public function test_order_number_search_works_with_customer_and_date_filters(): void
    {
        $order = ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-25']);
        $controller = new OrderManagementController;
        foreach ([$order->number, strtolower($order->number), '000001', '1', 'om', 'OM-', 'om-000', '0000'] as $search) {
            $orders = $controller->index($this->request(['search'=>$search,'customer_id'=>1,'from_date'=>'2026-11-25','to_date'=>'2026-11-25'], 'GET'), 'one')->getData()['orders'];
            $this->assertSame(1, $orders->total());
            $this->assertSame($order->id, $orders->first()->id);
            $url = $orders->url(2);
            $this->assertStringContainsString('from_date=2026-11-25', $url);
            $this->assertStringContainsString('to_date=2026-11-25', $url);
        }
    }

    public function test_reversed_date_range_is_rejected(): void
    {
        $this->expectException(ValidationException::class);
        (new OrderManagementController)->index($this->request(['from_date'=>'2026-11-26','to_date'=>'2026-11-25'], 'GET'), 'one');
    }

    public function test_partial_order_number_search_does_not_bypass_company_customer_or_date_filters(): void
    {
        DB::table('customers')->insert(['id'=>3,'company_id'=>1,'name'=>'Bob']);
        $match = ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-25']);
        ManagedOrder::create(['company_id'=>1,'customer_id'=>3,'order_date'=>'2026-11-25']);
        ManagedOrder::create(['company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-24']);
        ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        $orders = (new OrderManagementController)->index($this->request(['search'=>'om','customer_id'=>1,'from_date'=>'2026-11-25'], 'GET'), 'one')->getData()['orders'];
        $this->assertSame([$match->id], $orders->pluck('id')->all());
    }

    public function test_display_number_search_does_not_truncate_ids_longer_than_six_digits(): void
    {
        DB::table('managed_orders')->insert(['id'=>1234567,'company_id'=>1,'customer_id'=>1,'order_date'=>'2026-11-25']);
        $orders = (new OrderManagementController)->index($this->request(['search'=>'OM-1234567'], 'GET'), 'one')->getData()['orders'];
        $this->assertSame(1, $orders->total());
    }

    private function apiRequest(array $data = [], string $method = 'GET', bool $admin = true, array $permissions = []): Request
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>1,'company_id'=>1]);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn($admin);
        $user->shouldReceive('hasAnyPermission')->andReturnUsing(fn ($names) => (bool) array_intersect($names, $permissions));
        $request = Request::create('/api/order-management', $method, $data);
        $request->headers->set('Accept','application/json');
        $request->setUserResolver(fn () => $user);
        return $request;
    }

    public function test_order_api_requires_login_and_uses_existing_protection_middleware(): void
    {
        $this->getJson('/api/order-management')->assertStatus(401);
        $route = app('router')->getRoutes()->getByName('api.order-management.store');
        foreach (['auth:sanctum','single.api.token','company.active','worker.office.access'] as $middleware) $this->assertContains($middleware, $route->gatherMiddleware());
    }

    public function test_order_api_creates_reads_updates_lists_and_generates_pdf(): void
    {
        $api = new \App\Http\Controllers\Api\OrderManagementApiController;
        $stock = DB::table('item_sets')->get()->toJson();
        $created = $api->store($this->apiRequest($this->data(), 'POST'));
        $this->assertSame(201,$created->getStatusCode());
        $data = $created->getData(true)['data'];
        $this->assertSame('OM-000001',$data['number']);
        $this->assertSame(2,$data['totals']['quantity']);
        $this->assertArrayHasKey('available_quantity',$data['items'][0]);
        $this->assertSame($data['id'],$api->show($this->apiRequest(),$data['id'])->getData(true)['data']['id']);
        $updated = $api->update($this->apiRequest($this->data(['remarks'=>'API updated']), 'PUT'),$data['id'])->getData(true);
        $this->assertSame('API updated',$updated['data']['remarks']);
        $list = $api->index($this->apiRequest(['search'=>'om','from_date'=>'2026-11-25','per_page'=>10]))->getData(true);
        $this->assertSame(1,$list['data']['total']);
        $this->assertSame(10,$list['data']['per_page']);
        $this->assertStringContainsString('/api/order-management',$list['data']['path']);
        $this->assertSame('application/pdf',$api->pdf($this->apiRequest(),$data['id'])->headers->get('Content-Type'));
        $this->assertSame($stock,DB::table('item_sets')->get()->toJson());
    }

    public function test_order_api_options_and_search_are_scoped_and_exclude_selected_labels(): void
    {
        $api = new \App\Http\Controllers\Api\OrderManagementApiController;
        $options = $api->options($this->apiRequest())->getData(true)['data'];
        $this->assertSame([1],array_column($options['customers'],'id'));
        $this->assertSame([1],array_column($options['items'],'id'));
        $this->assertCount(1,$api->search($this->apiRequest(['keyword'=>'ABC123']))->getData(true)['data']);
        $this->assertSame([],$api->search($this->apiRequest(['keyword'=>'ABC123','exclude'=>[1]]))->getData(true)['data']);
    }

    public function test_order_api_can_create_customer_inline(): void
    {
        $api = new \App\Http\Controllers\Api\OrderManagementApiController;
        $response = $api->store($this->apiRequest($this->data(['customer_id'=>'other','new_customer'=>['name'=>'API customer','email'=>'api@example.test']]),'POST'))->getData(true);
        $this->assertSame('API customer',$response['data']['customer']['name']);
        $this->assertSame(1,(int) $response['data']['company_id']);
        $this->assertSame(1,DB::table('customers')->where('company_id',2)->count());
    }

    public function test_order_api_does_not_allow_view_only_staff_to_create_orders(): void
    {
        $this->expectException(HttpException::class);
        (new \App\Http\Controllers\Api\OrderManagementApiController)->store($this->apiRequest($this->data(),'POST',false,['order-management-view']));
    }

    public function test_order_api_does_not_allow_company_override_or_cross_company_order_access(): void
    {
        $api = new \App\Http\Controllers\Api\OrderManagementApiController;
        try { $api->index($this->apiRequest(['company_id'=>2])); $this->fail('Expected forbidden'); }
        catch (HttpException $e) { $this->assertSame(403,$e->getStatusCode()); }
        $other = ManagedOrder::create(['company_id'=>2,'customer_id'=>2,'order_date'=>'2026-11-25']);
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        $api->show($this->apiRequest(),$other->id);
    }

    public function test_order_api_accepts_authenticated_json_requests_through_routes(): void
    {
        // These existing middlewares depend on full company/office fixtures; their registration is checked above.
        $this->withoutMiddleware([\App\Http\Middleware\EnsureCompanyIsActive::class, \App\Http\Middleware\CheckWorkerOfficeAccess::class]);
        $this->actingAs($this->apiRequest()->user(), 'sanctum');
        $created = $this->postJson('/api/order-management', $this->data())->assertStatus(201)->assertJsonPath('success',true)->assertJsonPath('data.number','OM-000001');
        $id = $created->json('data.id');
        $this->getJson('/api/order-management/'.$id)->assertOk()->assertJsonPath('data.totals.quantity',2);
        $this->getJson('/api/order-management?search=om')->assertOk()->assertJsonPath('data.total',1);
        $this->putJson('/api/order-management/'.$id,$this->data(['remarks'=>'HTTP updated']))->assertOk()->assertJsonPath('data.remarks','HTTP updated');
        $this->postJson('/api/order-management', $this->data(['items'=>[]]))->assertStatus(422)->assertJsonPath('success',false);
    }

    public function test_order_api_accepts_form_data_and_update_method_override(): void
    {
        Request::enableHttpMethodParameterOverride();
        $this->withoutMiddleware([\App\Http\Middleware\EnsureCompanyIsActive::class, \App\Http\Middleware\CheckWorkerOfficeAccess::class]);
        $this->actingAs($this->apiRequest()->user(), 'sanctum');
        $headers = ['Accept'=>'application/json'];
        $form = ['customer_id'=>'1','order_date'=>'2026-11-25','remarks'=>'Form-data order','items'=>[['item_set_id'=>'1','quantity'=>'1']]];
        $created = $this->post('/api/order-management',$form,$headers)->assertStatus(201)->assertJsonPath('data.remarks','Form-data order');
        $id = $created->json('data.id');
        $form['_method'] = 'PUT'; $form['remarks'] = 'Updated through form-data';
        $this->post('/api/order-management/'.$id,$form,$headers)->assertOk()->assertJsonPath('data.remarks','Updated through form-data');
        $this->assertSame(1,ManagedOrder::count());
        unset($form['_method']);
        $form['customer_id'] = 'other'; $form['new_customer'] = ['name'=>'Form customer','mobile_no'=>'9876543210'];
        $this->post('/api/order-management',$form,$headers)->assertStatus(201)->assertJsonPath('data.customer.name','Form customer');
    }

    public function test_pdf_shows_wholesale_price_and_image_before_quantity(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->boolean('wholesale_price_enabled')->default(false));
        Schema::table('item_sets', function (Blueprint $t) {
            $t->decimal('wholesale_price',15,2)->nullable(); $t->integer('quantity')->default(1);
            $t->string('image_path')->nullable(); $t->string('image_disk')->nullable(); $t->string('image_mime')->nullable();
        });
        DB::table('companies')->where('id',1)->update(['wholesale_price_enabled'=>true]);
        \Illuminate\Support\Facades\Storage::fake('public');
        config(['cache.stores.file'=>['driver'=>'array']]);
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aP9sAAAAASUVORK5CYII=');
        if (function_exists('imagecreatetruecolor')) {
            $img = imagecreatetruecolor(100,100);
            imagefill($img,0,0,imagecolorallocate($img,250,250,250));
            imagefilledellipse($img,50,50,70,70,imagecolorallocate($img,195,149,48));
            imagefilledellipse($img,50,50,44,44,imagecolorallocate($img,250,250,250));
            ob_start(); imagepng($img); $png = ob_get_clean(); imagedestroy($img);
        }
        \Illuminate\Support\Facades\Storage::disk('public')->put('order-test/ring.png',$png);
        DB::table('item_sets')->where('id',1)->update(['wholesale_price'=>1234.50,'image_path'=>'order-test/ring.png','image_disk'=>'public','image_mime'=>'image/png']);
        $controller = new OrderManagementController;
        $search = $controller->search($this->request(['keyword'=>'ABC123'],'GET'),'one')->getData(true)[0];
        $this->assertEquals(1234.50,$search['wholesale_price']);
        $this->assertStringContainsString('ring.png',$search['image_url']);
        $controller->store($this->request($this->data(['price_mode'=>'wholesale'])),'one');
        $order = ManagedOrder::with('items','customer')->firstOrFail();
        $company = \App\Models\Company::findOrFail(1);
        $set = \App\Models\ItemSet::findOrFail(1);
        $order->items[0]->setAttribute('display_wholesale_price',$set->wholesale_price);
        $order->items[0]->setAttribute('pdf_image',app(\App\Services\StockGalleryPdfImage::class)->dataUri($set));
        $html = view('company.order_management.pdf',compact('company','order'))->render();
        $this->assertStringContainsString('Wholesale Price',$html);
        $this->assertStringContainsString('1,234.50',$html);
        $this->assertStringContainsString('<img',$html);
        $this->assertStringContainsString('data:image/',$html);
        $this->assertLessThan(strpos($html,'<th class="qty">Qty</th>'),strpos($html,'<th class="image">Image</th>'));
        foreach (['Purity','Waste %','Net Purity','Fine Wt','Metal Rate','Labour Rate'] as $heading) $this->assertStringNotContainsString('<th>'.$heading.'</th>',$html);
        $response = $controller->pdf($this->request([],'GET'),'one',$order->id);
        $this->assertStringStartsWith('%PDF-',$response->getContent());
        $apiData = (new \App\Http\Controllers\Api\OrderManagementApiController)->show($this->apiRequest(),$order->id)->getData(true)['data'];
        $this->assertEquals(1234.50,$apiData['items'][0]['wholesale_price']);
        $this->assertStringContainsString('ring.png',$apiData['items'][0]['image_url']);
        file_put_contents(storage_path('order-pdf-preview.pdf'),$response->getContent());
        DB::table('companies')->where('id',1)->update(['wholesale_price_enabled'=>false]);
        $this->assertNull($controller->search($this->request(['keyword'=>'ABC123'],'GET'),'one')->getData(true)[0]['wholesale_price']);
    }

    public function test_sale_and_wholesale_display_prices_are_distinct_without_changing_billing(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->boolean('wholesale_price_enabled')->default(false));
        Schema::table('item_sets', function (Blueprint $t) {
            $t->decimal('sale_price',15,2)->nullable(); $t->decimal('wholesale_price',15,2)->nullable(); $t->integer('quantity')->default(1);
        });
        DB::table('companies')->where('id',1)->update(['wholesale_price_enabled'=>true]);
        DB::table('item_sets')->where('id',1)->update(['sale_price'=>1200,'wholesale_price'=>900]);
        $controller = new OrderManagementController;
        $label = $controller->search($this->request(['keyword'=>'ABC123'],'GET'),'one')->getData(true)[0];
        $this->assertEquals(1200,$label['sale_price']);
        $this->assertEquals(900,$label['wholesale_price']);
        $this->assertEquals(900,$label['billing_sale_price']);
        $controller->store($this->request($this->data(['price_mode'=>'wholesale'])),'one');
        $order = ManagedOrder::with('items','customer')->firstOrFail();
        $this->assertEquals(1800,$order->items[0]->total_amount);
        $rows = $controller->edit($this->request([],'GET'),'one',$order->id)->getData()['rows'];
        $this->assertEquals(1200,$rows[0]['sale_price']);
        $this->assertEquals(900,$rows[0]['billing_sale_price']);
        $api = (new \App\Http\Controllers\Api\OrderManagementApiController)->show($this->apiRequest(),$order->id)->getData(true)['data'];
        $this->assertEquals(1200,$api['items'][0]['sale_price']);
        $this->assertEquals(900,$api['items'][0]['billing_sale_price']);
        $order->items[0]->setAttribute('display_sale_price',1200);
        $order->items[0]->setAttribute('display_wholesale_price',900);
        $company = \App\Models\Company::findOrFail(1);
        $html = view('company.order_management.pdf',compact('company','order'))->render();
        $this->assertStringNotContainsString('<th>Sale Price</th>',$html);
        $this->assertStringContainsString('900.00',$html);
        $this->assertEquals(1200,DB::table('item_sets')->where('id',1)->value('sale_price'));
    }

    public function test_order_price_mode_changes_total_is_saved_and_is_returned_by_api(): void
    {
        Schema::table('companies', fn (Blueprint $t) => $t->boolean('wholesale_price_enabled')->default(false));
        Schema::table('item_sets', function (Blueprint $t) { $t->decimal('sale_price',15,2)->nullable(); $t->decimal('wholesale_price',15,2)->nullable(); $t->integer('quantity')->default(1); });
        DB::table('companies')->where('id',1)->update(['wholesale_price_enabled'=>true]);
        DB::table('item_sets')->where('id',1)->update(['sale_price'=>1200,'wholesale_price'=>900]);
        $stock = DB::table('item_sets')->get()->toJson();
        $controller = new OrderManagementController;
        $controller->store($this->request($this->data()),'one');
        $order = ManagedOrder::firstOrFail();
        $this->assertSame('sale',$order->price_mode);
        $this->assertEquals(2400,$order->items()->first()->total_amount);
        $controller->store($this->request($this->data(['price_mode'=>'wholesale']),'PUT'),'one',$order->id);
        $this->assertSame('wholesale',$order->fresh()->price_mode);
        $this->assertEquals(1800,$order->items()->first()->total_amount);
        $edit = $controller->edit($this->request([],'GET'),'one',$order->id)->getData();
        $this->assertSame('wholesale',$edit['priceMode']);
        $api = (new \App\Http\Controllers\Api\OrderManagementApiController)->show($this->apiRequest(),$order->id)->getData(true)['data'];
        $this->assertSame('wholesale',$api['price_mode']);
        $this->assertEquals(1800,$api['totals']['amount']);
        $response = $controller->pdf($this->request([],'GET'),'one',$order->id);
        $this->assertStringStartsWith('%PDF-',$response->getContent());
        file_put_contents(storage_path('order-price-mode-preview.pdf'),$response->getContent());
        $this->assertSame($stock,DB::table('item_sets')->get()->toJson());
    }

    public function test_price_modes_use_per_piece_values_and_fall_back_to_available_price(): void
    {
        $defaults = ['sale_price'=>1200,'wholesale_price'=>900,'sale_unit_price'=>600,'wholesale_unit_price'=>450];
        $this->assertEquals(1200,\App\Services\OrderManagementPricing::calculate(['quantity'=>2],$defaults,'sale')['total_amount']);
        $this->assertEquals(900,\App\Services\OrderManagementPricing::calculate(['quantity'=>2],$defaults,'wholesale')['total_amount']);
        $defaults['wholesale_unit_price'] = 0;
        $this->assertEquals(1200,\App\Services\OrderManagementPricing::calculate(['quantity'=>2],$defaults,'wholesale')['total_amount']);
    }

    public function test_disabled_wholesale_mode_is_rejected_without_creating_customer_or_order(): void
    {
        try {
            (new OrderManagementController)->store($this->request($this->data(['price_mode'=>'wholesale','customer_id'=>'other','new_customer'=>['name'=>'Not created']])),'one');
            $this->fail('Expected wholesale validation failure');
        } catch (ValidationException $e) { $this->assertArrayHasKey('price_mode',$e->errors()); }
        $this->assertSame(0,ManagedOrder::count());
        $this->assertSame(2,DB::table('customers')->count());
    }

    public function test_order_quantity_can_exceed_stock_and_api_keeps_stock_unchanged(): void
    {
        DB::table('item_sets')->where('id',1)->update(['available_quantity'=>1]);
        $before = DB::table('item_sets')->get()->toJson();
        $api = new \App\Http\Controllers\Api\OrderManagementApiController;
        $data = $this->data(['items'=>[['item_set_id'=>1,'quantity'=>50,'metal_rate'=>100]]]);
        $response = $api->store($this->apiRequest($data,'POST'))->getData(true)['data'];
        $this->assertSame(50,$response['items'][0]['quantity']);
        $this->assertEquals(20000,$response['totals']['amount']);
        $data['items'][0]['quantity'] = 75;
        $updated = $api->update($this->apiRequest($data,'PUT'),$response['id'])->getData(true)['data'];
        $this->assertSame(75,$updated['items'][0]['quantity']);
        $this->assertSame($before,DB::table('item_sets')->get()->toJson());
    }
}
