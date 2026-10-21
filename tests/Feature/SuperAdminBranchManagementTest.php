<?php
namespace Tests\Feature;

use Tests\TestCase;
use App\Models\{Company, User};
use App\Http\Controllers\SuperAdmin\BranchManagementController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SuperAdminBranchManagementTest extends TestCase
{
    public function test_company_switch_does_not_depend_on_admin_user_status(): void
    {
        $company = Company::find(1);
        $company->setRelation('users', collect([(new User)->forceFill(['is_active' => 0])]));
        $method = new \ReflectionMethod(\App\Http\Controllers\SuperAdmin\CompanyController::class, 'companyEffectiveStatus');
        $controller = new \App\Http\Controllers\SuperAdmin\CompanyController;
        $this->assertTrue($method->invoke($controller, $company));
        $company->status = 0;
        $this->assertFalse($method->invoke($controller, $company));
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE companies (id integer primary key, parent_company_id integer, name text, status integer, updated_at text)');
        DB::statement('CREATE TABLE users (id integer primary key, company_id integer, name text, email text, email_verified_at text, is_active integer, two_factor_secret text, two_factor_enabled integer, two_factor_recovery_codes text, active_web_session_id text, active_api_token_id integer, remember_token text, updated_at text)');
        DB::statement('CREATE TABLE personal_access_tokens (id integer primary key, tokenable_id integer, tokenable_type text, token text)');
        foreach ([1=>null, 2=>1, 3=>null] as $id=>$parent) {
            DB::table('companies')->insert(['id'=>$id,'parent_company_id'=>$parent,'name'=>'Company '.$id,'status'=>1]);
        }
        foreach ([1=>2,2=>3] as $id=>$company) {
            DB::table('users')->insert(['id'=>$id,'company_id'=>$company,'name'=>'User','email'=>"user$id@example.com",'is_active'=>1,'two_factor_secret'=>'secret','two_factor_enabled'=>1,'two_factor_recovery_codes'=>'[]','active_web_session_id'=>'old','remember_token'=>'old']);
            DB::table('personal_access_tokens')->insert(['id'=>$id,'tokenable_id'=>$id,'tokenable_type'=>User::class,'token'=>hash('sha256', 'test-secret-'.$id)]);
        }
    }

    public function test_branch_deactivation_revokes_access_but_preserves_user_settings(): void
    {
        $controller = new BranchManagementController;
        $controller->status(Request::create('/', 'PUT', ['status'=>0]), Company::find(1), Company::find(2));
        $this->assertSame(0, Company::find(2)->status);
        $this->assertSame(1, User::find(1)->is_active);
        $this->assertSame('secret', User::find(1)->two_factor_secret);
        $this->assertNotSame('old', User::find(1)->active_web_session_id);
        $this->assertSame(0, User::find(1)->tokens()->count());
        $this->assertSame('COMPANY_INACTIVE', \App\Services\InactiveTokenResponse::code('1|test-secret-1'));
        $this->assertNull(\App\Services\InactiveTokenResponse::code('1|wrong-secret'));
        $this->assertSame(1, User::find(2)->tokens()->count());
    }

    public function test_user_deactivation_resets_2fa_and_email_change_revokes_access(): void
    {
        $controller = new BranchManagementController;
        $controller->updateUser(Request::create('/', 'PUT', ['name'=>'Updated','email'=>'new@example.com','is_active'=>0]), Company::find(1), Company::find(2), User::find(1));
        $user = User::find(1);
        $this->assertSame('new@example.com', $user->email);
        $this->assertNull($user->two_factor_secret);
        $this->assertNull($user->two_factor_recovery_codes);
        $this->assertSame(0, $user->is_active);
        $this->assertSame(0, $user->tokens()->count());
    }

    public function test_other_branch_user_cannot_be_changed(): void
    {
        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        (new BranchManagementController)->updateUser(Request::create('/', 'PUT'), Company::find(1), Company::find(2), User::find(2));
    }
}
