<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use App\Services\CompanyModules;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class CompanyModulesTest extends TestCase
{
    public function test_module_migration_and_selection_storage(): void
    {
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        \Illuminate\Support\Facades\Schema::create('companies', function ($table) {
            $table->id();
            $table->timestamps();
        });
        $migration = require database_path('migrations/2026_10_22_120000_add_allowed_modules_to_companies.php');
        $migration->up();
        $company = Company::create(['allowed_modules' => ['jobwork', 'items']]);
        $this->assertSame(['jobwork', 'items'], $company->fresh()->allowed_modules);
        $company->update(['allowed_modules' => []]);
        $this->assertSame([], $company->fresh()->allowed_modules);
        $migration->down();
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('companies', 'allowed_modules'));
    }

    public function test_legacy_and_explicit_empty_selections_are_different(): void
    {
        $company = new Company;
        $this->assertSame(array_keys(config('company_modules')), CompanyModules::allowed($company));
        $company->allowed_modules = [];
        $this->assertSame([], CompanyModules::allowed($company));
        $this->assertFalse(CompanyModules::permissionAllowed($company, 'item-view'));
        $this->assertTrue(CompanyModules::permissionAllowed($company, 'dashboard-view'));
    }

    public function test_branch_inherits_parent_selection(): void
    {
        $parent = new Company(['allowed_modules' => ['jobwork']]);
        $branch = new Company(['allowed_modules' => ['sales']]);
        $branch->parent_company_id = 10;
        $branch->setRelation('moduleParent', $parent);
        $this->assertTrue(CompanyModules::permissionAllowed($branch, 'jobwork-issue-create'));
        $this->assertFalse(CompanyModules::permissionAllowed($branch, 'sale-view'));
        $parent->allowed_modules = ['sales'];
        $this->assertFalse(CompanyModules::permissionAllowed($branch, 'jobwork-issue-create'));
    }

    public function test_permission_aliases_and_disabled_assignment(): void
    {
        $company = new Company(['allowed_modules' => ['items', 'user-management']]);
        $this->assertTrue(CompanyModules::permissionAllowed($company, 'view_item_set'));
        $this->assertFalse(CompanyModules::permissionAllowed($company, 'castingheating-view'));
        $request = $this->request($company, 'RoleApiController', 'POST', ['permissions' => ['sale-create']]);
        try {
            CompanyModules::enforce($request);
            $this->fail('Disabled permission was accepted');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $this->assertSame(403, $e->getResponse()->getStatusCode());
        }
    }

    public function test_disabled_api_and_web_requests_are_rejected_and_essential_profile_is_allowed(): void
    {
        $company = new Company(['allowed_modules' => ['items']]);
        CompanyModules::enforce($this->request($company, 'ItemController'));
        CompanyModules::enforce($this->request($company, 'AuthController'));
        try {
            CompanyModules::enforce($this->request($company, 'SaleApiController'));
            $this->fail('Disabled module reached API controller');
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            $this->assertSame('MODULE_NOT_ENABLED', $e->getResponse()->getData(true)['code']);
        }
        $request = $this->request($company, 'SaleController', 'GET', [], false);
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        CompanyModules::enforce($request);
    }

    public function test_visiting_cards_are_separate_from_reports(): void
    {
        $company = new Company(['allowed_modules' => ['visiting-cards']]);
        $request = $this->request($company, 'ReportController', 'GET', [], false);
        $request->route()->name('company.reports.visiting-cards.index');
        $this->assertSame('visiting-cards', CompanyModules::requestModule($request));
        CompanyModules::enforce($request);
        $request = $this->request($company, 'ReportController', 'GET', [], false);
        $request->route()->name('company.reports.sales-summary.index');
        $this->assertSame('reports', CompanyModules::requestModule($request));
    }

    private function request(Company $company, string $controller, string $method = 'GET', array $data = [], bool $api = true): Request
    {
        $user = new User(['company_id' => 1]);
        $user->setRelation('company', $company);
        $request = Request::create($api ? '/api/test' : '/company/test', $method, $data);
        $request->setUserResolver(fn () => $user);
        $route = new Route([$method], $api ? 'api/test' : 'company/test', ['uses' => 'App\\Http\\Controllers\\'.($api ? 'Api' : 'Company').'\\'.$controller.'@index']);
        $route->setAction(array_merge($route->getAction(), ['controller' => $route->getAction('uses')]));
        $request->setRouteResolver(fn () => $route);
        return $request;
    }
}
