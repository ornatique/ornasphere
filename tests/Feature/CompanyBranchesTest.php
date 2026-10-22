<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Company, User};
use App\Services\CompanyBranches;
use App\Http\Controllers\Company\CompanyDashboardController;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class CompanyBranchesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        foreach ([
            'companies'=>'id integer primary key, name text, slug text, status integer',
            'users'=>'id integer primary key, company_id integer',
            'customers'=>'id integer primary key, company_id integer, is_active integer, name text',
            'sales'=>'id integer primary key, company_id integer, customer_id integer, voucher_no text, sale_date text, net_total real',
            'sale_returns'=>'id integer primary key, company_id integer, return_date text, return_total real',
            'approval_headers'=>'id integer primary key, company_id integer, customer_id integer, approval_date text, status text',
            'approval_items'=>'id integer primary key, approval_id integer, status text',
            'item_sets'=>'id integer primary key, company_id integer, is_final integer, is_sold integer',
        ] as $table=>$columns) DB::statement("CREATE TABLE $table ($columns)");
        (require database_path('migrations/2026_10_13_180000_add_company_branches.php'))->up();
        foreach ([1=>null,2=>1,3=>1,4=>null,5=>4] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'parent_company_id'=>$parent,'name'=>'Company '.$id,'slug'=>'company-'.$id,'status'=>1]);
            DB::table('sales')->insert(['id'=>$id,'company_id'=>$id,'sale_date'=>now()->toDateString(),'net_total'=>$id*100,'voucher_no'=>'S'.$id]);
        }
    }

    public function test_management_branch_access_is_limited_to_children_and_three_controllers(): void
    {
        DB::table('companies')->where('id',3)->update(['status'=>0]);
        foreach ([['company-2', \App\Http\Controllers\Company\CustomerController::class, true],
                  ['company-2', \App\Http\Controllers\Company\CompanyUserController::class, true],
                  ['company-2', \App\Http\Controllers\Company\CompanyRoleController::class, true],
                  ['company-3', \App\Http\Controllers\Company\CustomerController::class, false],
                  ['company-4', \App\Http\Controllers\Company\CustomerController::class, false],
                  ['company-2', \App\Http\Controllers\Company\CompanyDashboardController::class, false]] as [$slug, $controller, $allowed]) {
            $request = Request::create('/company/'.$slug.'/customers');
            $route = new \Illuminate\Routing\Route('GET', 'company/{slug}/customers', ['uses'=>$controller.'@index', 'controller'=>$controller.'@index']);
            $route->bind($request);
            $request->setRouteResolver(fn() => $route);
            $request->setUserResolver(fn() => $this->user(1));
            try {
                $response = (new \App\Http\Middleware\EnsureCompanyIsActive)->handle($request, fn() => response('allowed'));
                $this->assertTrue($allowed);
                $this->assertSame('allowed', $response->getContent());
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertFalse($allowed);
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_management_multiselect_rejects_unrelated_inactive_and_unprivileged_branches(): void
    {
        DB::table('companies')->where('id', 3)->update(['status' => 0]);
        foreach ([[1, true, [1, 2], true], [1, true, [4], false], [1, true, [3], false],
                  [2, true, [1, 2], false], [1, false, [2], false], [2, true, [2], true]] as [$company, $admin, $ids, $allowed]) {
            $request = Request::create('/', 'GET', ['management_branches' => $ids]);
            $request->setUserResolver(fn () => $this->user($company, $admin));
            try {
                $selected = CompanyBranches::managementSelection($request, Company::findOrFail($company));
                $this->assertTrue($allowed);
                $this->assertEqualsCanonicalizing($ids, $selected->keys()->all());
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertFalse($allowed);
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }

    public function test_management_roles_combines_selected_branches_with_owner_action_urls(): void
    {
        DB::statement('CREATE TABLE roles (id integer primary key, company_id integer, name text, guard_name text)');
        DB::statement('CREATE TABLE model_has_roles (role_id integer, model_id integer, model_type text)');
        foreach ([1, 2, 3, 4] as $id) DB::table('roles')->insert(['id'=>$id, 'company_id'=>$id, 'name'=>'Role '.$id, 'guard_name'=>'web']);
        DB::table('users')->insert(['id'=>12, 'company_id'=>2]);
        DB::table('model_has_roles')->insert(['role_id'=>2, 'model_id'=>12, 'model_type'=>User::class]);
        $request = Request::create('/company/company-1/roles', 'GET', ['management_branches'=>[1,2]], [], [], ['HTTP_X_REQUESTED_WITH'=>'XMLHttpRequest']);
        $request->setUserResolver(fn () => $this->user(1));
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $response = (new \App\Http\Controllers\Company\CompanyRoleController)->index('company-1', $request);
        $data = $response->getData(true);
        $this->assertSame(2, $data['recordsTotal']);
        $rows = collect($data['data'])->keyBy('company_id');
        $this->assertEqualsCanonicalizing([1, 2], $rows->keys()->all());
        $this->assertSame('Company 2', $rows[2]['company_name']);
        $this->assertSame(1, $rows[2]['users_count']);
        $this->assertStringContainsString('/company/company-2/', $rows[2]['action']);
        $this->assertStringNotContainsString('/company/company-1/', $rows[2]['action']);
        DB::statement('CREATE TABLE permissions (id integer primary key, name text, guard_name text, company_id integer, created_at text, updated_at text)');
        DB::statement('CREATE TABLE role_has_permissions (role_id integer, permission_id integer)');
        $api = (new \App\Http\Controllers\Api\RoleApiController)->index($request)->getData(true);
        $this->assertEqualsCanonicalizing([1,2], $api['selected_company_ids']);
        $this->assertCount(2, $api['data']);
        $apiRows = collect($api['data'])->keyBy('company_id');
        $this->assertSame(1, $apiRows[2]['users_count']);
        $this->assertSame('Company 2', $apiRows[2]['company_name']);
    }

    public function test_api_management_lists_default_to_own_company_and_combine_explicit_selection(): void
    {
        DB::statement('ALTER TABLE users ADD COLUMN created_at text');
        DB::statement('ALTER TABLE users ADD COLUMN two_factor_secret text');
        DB::statement('ALTER TABLE customers ADD COLUMN created_at text');
        foreach ([1,2,3,4] as $id) {
            DB::table('users')->insert(['id'=>$id, 'company_id'=>$id, 'two_factor_secret'=>'private']);
            DB::table('customers')->insert(['id'=>$id, 'company_id'=>$id, 'name'=>'Person '.$id]);
        }
        foreach ([\App\Http\Controllers\Api\CompanyUserController::class, \App\Http\Controllers\Api\CustomerApiController::class] as $controller) {
            foreach ([[[], [1]], [['management_branches'=>[1,2]], [1,2]], [['company_id'=>2], [2]]] as [$input, $ids]) {
                $request = Request::create('/', 'GET', $input);
                $request->setUserResolver(fn () => $this->user(1));
                $data = (new $controller)->index($request)->getData(true);
                $this->assertEqualsCanonicalizing($ids, array_column($data['data'], 'company_id'));
                $this->assertEqualsCanonicalizing($ids, $data['selected_company_ids']);
                foreach ($data['data'] as $row) {
                    $this->assertSame('Company '.$row['company_id'], $row['company_name']);
                    $this->assertArrayNotHasKey('two_factor_secret', $row);
                }
            }
        }
    }

    public function test_api_management_actions_require_one_authorized_company(): void
    {
        foreach ([['company_id'=>2], ['management_branches'=>[2]]] as $input) {
            $request = Request::create('/', 'PUT', $input);
            $request->setUserResolver(fn () => $this->user(1));
            $this->assertSame(2, CompanyBranches::apiManagementCompanyId($request));
        }
        foreach ([['company_id'=>2, 'management_branches'=>[1]], ['management_branches'=>[1,2]], ['management_branches'=>[]], ['company_id'=>'bad']] as $input) {
            $request = Request::create('/', 'PUT', $input);
            $request->setUserResolver(fn () => $this->user(1));
            try {
                CompanyBranches::apiManagementCompanyId($request);
                $this->fail('Ambiguous or invalid selection was accepted');
            } catch (\Illuminate\Validation\ValidationException $e) {
                $this->assertNotEmpty($e->errors());
            }
        }
        $request = Request::create('/', 'PUT', ['company_id'=>4]);
        $request->setUserResolver(fn () => $this->user(1));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        CompanyBranches::apiManagementCompanyId($request);
    }

    private function user(int $company, bool $admin = true): User
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>20,'company_id'=>$company,'is_active'=>1]);
        $user->setRelation('company', Company::findOrFail($company));
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn($admin);
        return $user;
    }

    public function test_both_barcode_excel_endpoints_return_valid_workbooks_without_a_code(): void
    {
        foreach (['reports', 'branch-reports'] as $endpoint) {
            $request = Request::create('/api/'.$endpoint, 'GET', ['report_branches'=>'all','export'=>'excel']);
            $this->app->instance('request', $request);
            $request->setUserResolver(fn () => $this->user(1));
            $response = $endpoint === 'reports'
                ? (new \App\Http\Controllers\Api\ReportApiController)->barcodeHistoryExcel($request)
                : (new \App\Http\Controllers\Api\BranchReportApiController)->show($request, 'barcode-history');
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
            $path = $response->getFile()->getPathname();
            $zip = new \ZipArchive;
            $this->assertTrue($zip->open($path));
            $this->assertStringContainsString('Company / Branch', $zip->getFromName('xl/worksheets/sheet1.xml'));
            $this->assertStringContainsString('Barcode History', $zip->getFromName('xl/workbook.xml'));
            $zip->close();
            unlink($path);
        }
    }

    public function test_barcode_suggestions_include_selected_branches_without_merging_duplicate_codes(): void
    {
        DB::statement('CREATE TABLE items (id integer primary key, item_name text)');
        foreach (['item_id integer','qr_code text','barcode text','HUID text'] as $column) DB::statement('ALTER TABLE item_sets ADD COLUMN '.$column);
        foreach ([1,2,4] as $id) DB::table('item_sets')->insert(['id'=>$id,'company_id'=>$id,'qr_code'=>'RI1']);
        $request = Request::create('/api/reports/barcode-history/suggest', 'GET', ['q'=>'r','report_branches'=>'all']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $api = new \App\Http\Controllers\Api\ReportApiController;
        $rows = $api->barcodeHistorySuggest($request)->getData(true)['data'];
        $this->assertSame([2,1], array_column($rows, 'company_id'));
        $this->assertSame('Company 2', $rows[0]['branch_name']);
        $request->merge(['report_branches'=>'company-2']);
        $this->assertCount(1, $api->barcodeHistorySuggest($request)->getData(true)['data']);
        $request->merge(['report_branches'=>'company-4']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $api->barcodeHistorySuggest($request);
    }

    public function test_worker_loss_excel_api_streams_shared_web_export(): void
    {
        $request = Request::create('/api/reports/worker-loss/export/excel', 'GET', ['report_branches'=>'all']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $row = (object) ['company_id'=>2, 'process_datetime'=>null, 'worker_id'=>9,
            'worker_name'=>'Worker', 'voucher_no'=>'BR-1','buch_no'=>'B1','stage'=>'Jobwork Receive',
            'source_wt'=>50,'receive_wt'=>20,'bhuko'=>0,'loss'=>0];
        $query = \Mockery::mock(\Illuminate\Database\Query\Builder::class);
        $query->shouldReceive('get')->andReturn(collect([$row]));
        $web = \Mockery::mock(\App\Http\Controllers\Company\ReportController::class)->makePartial();
        $web->shouldReceive('workerLossBaseQuery')->andReturn($query);
        $this->app->instance(\App\Http\Controllers\Company\ReportController::class, $web);
        $response = (new \App\Http\Controllers\Api\ReportApiController)->workerLossExcel($request);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('spreadsheetml.sheet', $response->headers->get('Content-Type'));
        $path = $response->getFile()->getPathname();
        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($path));
        $csv = $zip->getFromName('xl/worksheets/sheet1.xml');
        $this->assertStringContainsString('Worker Loss', $zip->getFromName('xl/workbook.xml'));
        $zip->close();
        unlink($path);
        $this->assertStringContainsString('Company 2', $csv);
        $this->assertStringContainsString('BR-1', $csv);
        $this->assertStringContainsString('50.000', $csv);
        $this->assertStringContainsString('Branch', $csv);
    }

    public function test_worker_loss_api_uses_web_branch_query_and_filters(): void
    {
        $api = new \App\Http\Controllers\Api\ReportApiController;
        $method = new \ReflectionMethod($api, 'workerLossBaseQuery');
        foreach (['company-2', 'company-2,company-3', 'all'] as $selection) {
            $request = Request::create('/api/reports/worker-loss/export/pdf', 'GET', [
                'report_branches'=>$selection, 'from_date'=>'2026-10-10', 'to_date'=>'2026-10-16', 'only_loss'=>1,
            ]);
            $this->app->instance('request', $request);
            $request->setUserResolver(fn () => $this->user(1));
            $web = (new \App\Http\Controllers\Company\ReportController)->workerLossBaseQuery(Company::findOrFail(1), $request);
            $query = $method->invoke($api, $request, 1);
            $this->assertSame($web->toSql(), $query->toSql());
            $this->assertSame($web->getBindings(), $query->getBindings());
            $this->assertStringContainsString('company_id', $query->toSql());
        }
        $request->merge(['report_branches'=>'company-5']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $method->invoke($api, $request, 1);
    }

    public function test_approval_details_branch_scope_and_pending_filter(): void
    {
        $request = Request::create('/api/reports/approval-outstanding/10/details/pdf', 'GET', ['report_branches'=>'company-2,company-3']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $approval = \Mockery::mock(\App\Models\ApprovalHeader::class)->makePartial();
        $approval->forceFill(['id'=>10,'company_id'=>2,'approval_no'=>'BR-10']);
        $approval->setRelation('customer', null);
        $approval->setRelation('creator', null);
        $approval->shouldReceive('load')->once()->andReturnSelf();
        $items = \Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $items->shouldReceive('with')->once()->andReturnSelf();
        $items->shouldReceive('where')->once()->with('status', 'pending')->andReturnSelf();
        $items->shouldReceive('orderBy')->once()->with('id')->andReturnSelf();
        $items->shouldReceive('get')->once()->andReturn(collect());
        $approval->shouldReceive('items')->once()->andReturn($items);
        $api = new \App\Http\Controllers\Api\ReportApiController;
        $method = new \ReflectionMethod($api, 'approvalOutstandingDetailsPayload');
        $payload = $method->invoke($api, 1, $approval);
        $this->assertSame(2, $payload['approval']['company_id']);
        $this->assertSame('Company 2', $payload['approval']['branch_name']);
        $approval->company_id = 4;
        $this->assertNull($method->invoke($api, 1, $approval));
        $approval->company_id = 2;
        $request->query->remove('report_branches');
        $this->assertNull($method->invoke($api, 1, $approval));
        $request->merge(['report_branches'=>'company-3']);
        $request->setUserResolver(fn () => $this->user(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $method->invoke($api, 2, $approval);
    }

    public function test_sale_details_respects_selected_branches_and_identifies_voucher_owner(): void
    {
        $request = Request::create('/api/reports/sales-summary/10/details/pdf', 'GET', ['report_branches'=>'company-2,company-3']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $sale = \Mockery::mock(\App\Models\Sale::class)->makePartial();
        $sale->forceFill(['id'=>10,'company_id'=>2,'voucher_no'=>'BR-10']);
        $sale->setRelation('saleItems', collect());
        $sale->setRelation('customer', null);
        $sale->setRelation('creator', null);
        $sale->shouldReceive('load')->once()->andReturnSelf();
        $api = new \App\Http\Controllers\Api\ReportApiController;
        $method = new \ReflectionMethod($api, 'salesSummaryDetailsPayload');
        $payload = $method->invoke($api, 1, $sale);
        $this->assertSame(2, $payload['sale']['company_id']);
        $this->assertSame('Company 2', $payload['sale']['branch_name']);
        $sale->company_id = 4;
        $this->assertNull($method->invoke($api, 1, $sale));
        $sale->company_id = 2;
        $request->query->remove('report_branches');
        $this->assertNull($method->invoke($api, 1, $sale));
    }

    public function test_stock_details_api_returns_web_payload_and_delegates_exports(): void
    {
        $request = Request::create('/api/reports/stock-position/details', 'GET', [
            'item_id'=>7, 'stock_type'=>'customer_received', 'report_branches'=>'company-2',
        ]);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $request->attributes->set('stock_image_counts', ['with_image'=>0,'without_image'=>1]);
        $web = \Mockery::mock(\App\Http\Controllers\Company\ReportController::class);
        $web->shouldReceive('stockPositionDetails')->once()->with($request, 'company-1')->andReturn(
            view('company.reports.stock_position_details', [
                'rows'=>collect([(object)['company_id'=>2,'label_code'=>'LABEL-1','has_image'=>false]]),
                'summary'=>['qty_pcs'=>1], 'itemName'=>'Gold Necklace',
                'stockTypeName'=>'Customer Received', 'customerName'=>'Customer',
            ])
        );
        $this->app->instance(\App\Http\Controllers\Company\ReportController::class, $web);
        $api = new \App\Http\Controllers\Api\ReportApiController;
        $data = $api->stockPositionDetails($request)->getData(true);
        $this->assertSame(1, $data['count']);
        $this->assertSame('Company 2', $data['data'][0]['branch_name']);
        $this->assertSame(1, $data['image_counts']['without_image']);
        foreach (['pdf'=>'Pdf', 'excel'=>'Excel'] as $format=>$suffix) {
            $response = response()->streamDownload(fn () => print('export'), 'details.'.$format);
            $web->shouldReceive('stockPositionDetails'.$suffix)->once()->with($request, 'company-1')->andReturn($response);
            $this->assertSame($response, $api->stockPositionDetails($request, $format));
        }
        $request->merge(['report_branches'=>'company-5']);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $api->stockPositionDetails($request);
    }

    public function test_api_pdf_without_branch_selection_defaults_to_authenticated_workspace(): void
    {
        foreach ([1, 2] as $id) {
            $request = Request::create('/api/reports/sales-summary/export/pdf');
            $this->app->instance('request', $request);
            $request->setUserResolver(fn () => $this->user($id));
            $company = Company::select('id', 'name')->findOrFail($id);
            $this->assertSame([$id], \App\Services\ReportBranches::ids($company, $request));
            $this->assertSame('Company '.$id.($id === 1 ? ' - Company Admin' : ''),
                \App\Services\ReportBranches::heading($company, $request));
            $this->assertStringNotContainsString('Selected companies / branches',
                view('company.reports.partials.pdf_company_scope', compact('company'))->render());
        }
    }

    public function test_missing_selection_does_not_allow_foreign_company_scope(): void
    {
        $request = Request::create('/api/reports/sales-summary/export/pdf');
        $request->setUserResolver(fn () => $this->user(1));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Services\ReportBranches::ids(Company::findOrFail(4), $request);
    }

    public function test_parent_can_deactivate_and_reactivate_own_branch(): void
    {
        DB::statement('ALTER TABLE companies ADD COLUMN updated_at datetime');
        foreach ([0, 1] as $status) {
            $request = Request::create('/', 'PATCH', ['status' => $status]);
            $request->setUserResolver(fn () => $this->user(1));
            (new \App\Http\Controllers\Company\BranchController)->status($request, 'company-1', 2);
            $this->assertEquals($status, Company::find(2)->status);
            $this->assertEquals(1, Company::find(3)->status);
        }
    }

    public function test_api_status_returns_json_and_updates_only_owned_branch(): void
    {
        DB::statement('ALTER TABLE companies ADD COLUMN updated_at datetime');
        $request = Request::create('/api/company/branches/2/status', 'PATCH', ['status'=>0]);
        $request->setUserResolver(fn () => $this->user(1));
        $response = (new \App\Http\Controllers\Api\CompanyBranchApiController)->status($request, 2);
        $this->assertTrue($response->getData(true)['success']);
        $this->assertFalse($response->getData(true)['data']['status']);
        $this->assertEquals(1, Company::find(3)->status);
    }

    public function test_api_branch_customer_options_are_scoped_and_filter_token_is_usable(): void
    {
        foreach ([1,2,3,4,5] as $id) DB::table('customers')->insert(['id'=>$id,'company_id'=>$id,'is_active'=>1,'name'=>'Customer '.$id]);
        $request = Request::create('/api/company/branch-report-customers', 'GET', ['report_branches'=>'company-2,company-3']);
        $request->setUserResolver(fn () => $this->user(1));
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $data = (new \App\Http\Controllers\Api\CompanyBranchApiController)->customers($request)->getData(true)['data'];
        $this->assertSame([2,3], array_column($data, 'id'));
        $this->assertSame('2', \Illuminate\Support\Facades\Crypt::decryptString($data[0]['register_filter_id']));
    }

    public function test_parent_status_controls_effective_branch_access(): void
    {
        $this->assertTrue(CompanyBranches::isActive(Company::find(2)));
        DB::table('companies')->where('id',1)->update(['status'=>0]);
        $this->assertFalse(CompanyBranches::isActive(Company::find(2)));
        $this->assertEquals(1, Company::find(2)->status);
    }

    public function test_api_reports_reject_foreign_company_before_running_report(): void
    {
        $request = Request::create('/api/company/branch-reports/sales-summary', 'GET', ['report_branches'=>'company-5']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\BranchReportApiController)->show($request, 'sales-summary');
    }

    public function test_api_branch_admin_cannot_change_sibling_status(): void
    {
        $request = Request::create('/api/company/branches/3/status', 'PATCH', ['status'=>0]);
        $request->setUserResolver(fn () => $this->user(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Controllers\Api\CompanyBranchApiController)->status($request, 3);
    }

    public function test_parent_cannot_change_foreign_branch_status(): void
    {
        $request = Request::create('/', 'PATCH', ['status' => 0]);
        $request->setUserResolver(fn () => $this->user(1));
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        (new \App\Http\Controllers\Company\BranchController)->status($request, 'company-1', 5);
    }

    public function test_parent_sees_only_own_family_and_branch_admin_cannot_manage_branches(): void
    {
        $this->assertEquals([1,2,3], CompanyBranches::visibleCompanies($this->user(1))->pluck('id')->all());
        $this->assertEquals([2], CompanyBranches::visibleCompanies($this->user(2))->pluck('id')->all());
        $this->assertEquals([1], CompanyBranches::visibleCompanies($this->user(1, false))->pluck('id')->all());
        $this->assertFalse(CompanyBranches::canManage($this->user(2)));
    }

    public function test_dashboard_combines_owned_branches_and_filters_one_branch(): void
    {
        $this->actingAs($this->user(1));
        $controller = new CompanyDashboardController;
        $all = $controller->index(Request::create('/'))->getData();
        $this->assertEquals(600, $all['salesToday']);
        $one = $controller->index(Request::create('/', 'GET', ['branch'=>'company-2']))->getData();
        $this->assertEquals(200, $one['salesToday']);
        $this->assertEquals('Company 2', $one['recentActivity']->first()['branch']);
    }

    public function test_dashboard_rejects_other_company(): void
    {
        $this->actingAs($this->user(1));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CompanyDashboardController)->index(Request::create('/', 'GET', ['branch'=>'company-5']));
    }

    public function test_branch_cannot_request_all_branch_dashboard(): void
    {
        $this->actingAs($this->user(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new CompanyDashboardController)->index(Request::create('/', 'GET', ['branch'=>'all']));
    }

    public function test_company_admin_creates_branch_admin_and_defaults(): void
    {
        DB::table('companies')->where('id',3)->delete();
        foreach (['companies'=>['email','city','address_1','max_users','plan','plan_started_at','plan_expires_at','created_at','updated_at'],
            'users'=>['name','email','password','role','is_active','password_changed','created_at','updated_at']] as $table=>$columns) {
            foreach ($columns as $column) DB::statement("ALTER TABLE $table ADD COLUMN $column text");
        }
        DB::statement('CREATE TABLE roles (id integer primary key, name text, guard_name text, created_at text, updated_at text)');
        DB::statement('CREATE TABLE model_has_roles (role_id integer, model_type text, model_id integer)');
        DB::statement('CREATE TABLE category_people (id integer primary key, company_id integer, category_name text, is_system_default integer, created_at text, updated_at text)');
        DB::table('roles')->insert(['id'=>1,'name'=>'company_admin','guard_name'=>'web']);
        $request = Request::create('/', 'POST', ['name'=>'New Retail','branch_type'=>'retail','admin_name'=>'Branch Manager',
            'email'=>'branch@example.test','password'=>'LongBranchPassword123','password_confirmation'=>'LongBranchPassword123']);
        $request->setUserResolver(fn()=>$this->user(1));
        (new \App\Http\Controllers\Company\BranchController)->store($request, 'company-1');
        $branch = Company::where('name','New Retail')->firstOrFail();
        $this->assertEquals(1, $branch->parent_company_id);
        $this->assertEquals('retail', $branch->branch_type);
        $admin = User::where('company_id',$branch->id)->firstOrFail();
        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('LongBranchPassword123',$admin->password));
        $this->assertTrue($admin->hasRole('company_admin'));
        $this->assertEquals(2, DB::table('category_people')->where('company_id',$branch->id)->count());
        DB::table('companies')->where('id',2)->update(['status'=>0]);
        $request->merge(['name'=>'Third Branch','email'=>'third@example.test']);
        try {
            (new \App\Http\Controllers\Company\BranchController)->store($request, 'company-1');
            $this->fail('A third branch must be rejected, including when a branch is inactive.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertSame(CompanyBranches::LIMIT_MESSAGE, $exception->errors()['branch_limit'][0]);
        }
        $this->assertEquals(2, Company::where('parent_company_id',1)->count());
        $this->assertFalse(User::where('email','third@example.test')->exists());
    }

    public function test_branch_cannot_open_sibling_workspace_url(): void
    {
        $request = Request::create('/company/company-3/dashboard');
        $route = new \Illuminate\Routing\Route('GET', 'company/{slug}/dashboard', fn()=>null);
        $route->bind($request);
        $request->setRouteResolver(fn()=>$route);
        $request->setUserResolver(fn()=>$this->user(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new \App\Http\Middleware\EnsureCompanyIsActive)->handle($request, fn()=>response('unexpected'));
    }

    public function test_parent_inactive_blocks_branch_api_and_restores_access_on_reactivation(): void
    {
        $request = Request::create('/api/test', 'GET');
        $user = $this->user(2);
        $user->shouldReceive('currentAccessToken')->andReturn(null);
        $request->setUserResolver(fn()=>$user);
        $middleware = new \App\Http\Middleware\EnsureCompanyIsActive;
        DB::table('companies')->where('id',1)->update(['status'=>0]);
        $response = $middleware->handle($request, fn()=>response('allowed'));
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame('COMPANY_INACTIVE', $response->getData(true)['code']);
        $this->assertSame(1, (int) Company::find(2)->status);
        DB::table('companies')->where('id',1)->update(['status'=>1]);
        $this->assertSame(200, $middleware->handle($request, fn()=>response('allowed'))->getStatusCode());
        $user->company->status = 0;
        $this->assertSame(403, $middleware->handle($request, fn()=>response('allowed'))->getStatusCode());
    }

    public function test_child_plan_cannot_be_renewed_separately(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        $this->expectExceptionMessage('Renew the main company plan');
        (new \App\Http\Controllers\SuperAdmin\CompanyController)->renewPlan(Request::create('/', 'POST'), Company::find(2));
    }

    public function test_branch_list_groups_children_and_shows_inherited_inactive_status(): void
    {
        $company = Company::with('branches')->find(1);
        $html = view('superadmin.auth.company.branch-list', ['company'=>$company, 'parentActive'=>false])->render();
        $this->assertStringContainsString('View Branches (2)', $html);
        $this->assertStringContainsString('Company 2', $html);
        $this->assertStringNotContainsString('Company 5', $html);
        $this->assertStringContainsString('Main company is inactive', $html);
        $this->assertStringNotContainsString('renewPlanBtn', $html);
    }

    public function test_report_scope_supports_main_one_multiple_and_all_owned_branches(): void
    {
        foreach (['company-1'=>[1], 'company-2'=>[2], 'company-2,company-3'=>[2,3], 'all'=>[1,2,3]] as $selection=>$expected) {
            $request = Request::create('/', 'GET', ['report_branches'=>$selection]);
            $request->setUserResolver(fn()=>$this->user(1));
            $this->assertSame($expected, \App\Services\ReportBranches::ids(Company::find(1), $request));
        }
    }

    public function test_report_scope_rejects_foreign_branch(): void
    {
        $request = Request::create('/', 'GET', ['report_branches'=>'company-5']);
        $request->setUserResolver(fn()=>$this->user(1));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Services\ReportBranches::ids(Company::find(1), $request);
    }

    public function test_branch_admin_cannot_request_sibling_reports(): void
    {
        $request = Request::create('/', 'GET', ['report_branches'=>'company-3']);
        $request->setUserResolver(fn()=>$this->user(2));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Services\ReportBranches::ids(Company::find(2), $request);
    }

    public function test_main_company_staff_cannot_request_all_reports(): void
    {
        $request = Request::create('/', 'GET', ['report_branches'=>'all']);
        $request->setUserResolver(fn()=>$this->user(1, false));
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        \App\Services\ReportBranches::ids(Company::find(1), $request);
    }

    public function test_register_pdf_renders_multiple_company_rows_and_empty_results(): void
    {
        $request = Request::create('/', 'GET', ['report_branches'=>'company-1,company-2']);
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $this->user(1));
        $row = (object) ['voucher_id'=>1,'entry_date'=>'2026-10-14','entry_type'=>'Approval','voucher_no'=>'APP-1','approval_no'=>'APP-1','customer_name'=>'Customer','company_id'=>2,'qty'=>1];
        foreach (['Approval', null] as $type) {
            foreach ([collect([$row]), collect()] as $rows) {
                $html = view('company.reports.pdf.approval_return_register', [
                    'company'=>Company::find(1), 'filters'=>['entry_type'=>$type],
                    'metrics'=>['qty'=>'Qty'], 'totals'=>['Approval'=>['qty'=>1]], 'rows'=>$rows,
                    'pendingItems'=>collect(),
                ])->render();
                $this->assertStringContainsString('Company / Branch', $html);
                $this->assertStringContainsString($rows->isEmpty() ? 'No matching entries.' : 'APP-1', $html);
            }
        }
    }

    public function test_pdf_heading_matches_selected_companies(): void
    {
        foreach ([
            'company-1' => 'Company 1 - Company Admin',
            'company-2' => 'Company 2',
            'company-2,company-3' => 'Company 2 / Company 3',
            'all' => 'Company 1 - Company Admin / Company 2 / Company 3',
        ] as $selection => $expected) {
            $request = Request::create('/', 'GET', ['report_branches' => $selection]);
            $request->setUserResolver(fn () => $this->user(1));
            $this->assertSame($expected, \App\Services\ReportBranches::heading(Company::find(1), $request));
        }
        $request = Request::create('/', 'GET');
        $request->setUserResolver(fn () => $this->user(2));
        $this->assertSame('Company 2', \App\Services\ReportBranches::heading(Company::find(2), $request));
    }

    public function test_report_customer_options_follow_selected_branches(): void
    {
        foreach ([1,2,3,4,5] as $id) DB::table('customers')->insert(['id'=>$id,'company_id'=>$id,'is_active'=>1,'name'=>'Same Customer']);
        $request = Request::create('/', 'GET', ['report_branches'=>'company-2,company-3']);
        $request->setUserResolver(fn()=>$this->user(1));
        $this->app->instance('request', $request);
        $this->actingAs($this->user(1));
        $request->setUserResolver(fn() => auth()->user());
        $data = (new \App\Http\Controllers\Company\ReportController)->salesSummary($request, 'company-1')->getData();
        $this->assertEquals([2,3], $data['customers']->pluck('id')->sort()->values()->all());
        $this->assertEquals([2,3], $data['customers']->pluck('company_id')->sort()->values()->all());
        // Render the actual report body: variables assigned inside @include do not
        // propagate back to the parent Blade view.
        $template = str_replace("@extends('company_layout.admin')", '', file_get_contents(resource_path('views/company/reports/sales_summary.blade.php')));
        $html = \Illuminate\Support\Facades\Blade::render($template."\n@yield('content')", $data);
        $this->assertStringContainsString('Same Customer', $html);
        $this->assertStringContainsString('Company 2', $html);
    }
}
