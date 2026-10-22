<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\BranchReportApiController;
use App\Http\Controllers\Company\ReportController;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ApprovalRegisterApiTest extends TestCase
{
    public static function accessModes(): array
    {
        return [[true], [false]];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('accessModes')]
    public function test_api_exposes_pending_details_counts_and_customer_filter_values(bool $admin): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, name text, slug text, parent_company_id integer)');
        DB::table('companies')->insert(['id'=>1,'name'=>'Main','slug'=>'main']);
        $company = Company::findOrFail(1);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->forceFill(['id'=>1,'company_id'=>1]);
        $user->setRelation('company', $company);
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn($admin);
        $user->shouldReceive('hasAnyPermission')->with(['report-approval-outstanding-view'])->andReturn(true);
        $user->shouldReceive('hasAnyPermission')->with(['report-approval-summary-view'])->andReturn(true);
        $request = Request::create('/api/company/branch-reports/approval-return-register');
        $this->app->instance('request', $request);
        $request->setUserResolver(fn () => $user);
        $rows = new LengthAwarePaginator(collect([
            (object)['voucher_id'=>4,'company_id'=>1,'entry_type'=>'Approval'],
            (object)['voucher_id'=>4,'company_id'=>1,'entry_type'=>'Approval Return'],
        ]), 2, 10);
        $view = view('company.reports.approval_return_register', [
            'rows'=>$rows, 'metrics'=>['qty'=>'Qty'], 'totals'=>['Pending Approval (current)'=>['qty'=>2]],
            'pendingItems'=>collect([(object)['voucher_id'=>4,'qr_code'=>'A'],(object)['voucher_id'=>4,'qr_code'=>'B']]),
            'customers'=>collect([(object)['id'=>7,'name'=>'Customer','company_id'=>1]]), 'selectedCustomerId'=>null,
        ]);
        $mock = \Mockery::mock(ReportController::class);
        $mock->shouldReceive('approvalReturnRegister')->once()->with($request,'main')->andReturn($view);
        $this->app->instance(ReportController::class, $mock);
        $controller = new BranchReportApiController;
        $response = ($admin ? $controller->show($request,'approval-return-register') : $controller->ownRegister($request))->getData(true);
        $this->assertTrue($response['success']);
        $this->assertSame(2, $response['data']['pending_count']);
        $this->assertCount(2, $response['data']['pending_items']);
        $this->assertSame(2, $response['data']['rows']['data'][0]['pending_qty']);
        $this->assertNull($response['data']['rows']['data'][1]['pending_qty']);
        $this->assertSame('7', Crypt::decryptString($response['data']['customers'][0]['filter_value']));
        $this->assertSame('Pending Qty', $response['data']['columns']['pending_qty']);
        $this->assertSame(2, $response['data']['rows']['total']);
        if (!$admin) $this->assertSame('main', $request->input('report_branches'));
        $mock->shouldReceive('approvalSummary')->once()->with($request,'main')->andReturn($view);
        $summary = ($admin ? $controller->show($request,'approval-summary') : $controller->ownSummary($request))->getData(true);
        $this->assertTrue($summary['success']);
        $this->assertCount(1,$summary['data']['customers']);
    }

    public function test_staff_cannot_select_other_branches_or_bypass_report_permission(): void
    {
        foreach ([[true, 'all'], [true, 'other-company'], [false, null]] as [$permission, $selection]) {
            $company = new Company(['slug'=>'own-company']);
            $user = \Mockery::mock(User::class)->makePartial();
            $user->setRelation('company', $company);
            $user->shouldReceive('hasRole')->with('company_admin')->andReturn(false);
            $user->shouldReceive('hasAnyPermission')->with(['report-approval-outstanding-view'])->andReturn($permission);
            $request = Request::create('/api/reports/approval-return-register', 'GET', $selection ? ['report_branches'=>$selection] : []);
            $request->setUserResolver(fn () => $user);
            try {
                (new BranchReportApiController)->ownRegister($request);
                $this->fail('Unauthorized report request accepted');
            } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
                $this->assertSame(403, $e->getStatusCode());
            }
        }
    }
}
