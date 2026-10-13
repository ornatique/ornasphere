<?php

namespace Tests\Feature;

use App\Http\Controllers\Company\{CompanyAuthController, CompanySecurityController};
use App\Http\Middleware\{Company2FA, RedirectIfAuthenticated};
use App\Models\{Company, User};
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, Route, Schema};
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class CompanyTwoFactorLoginTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        Schema::create('companies', function (Blueprint $t) {
            $t->id(); $t->string('slug'); $t->integer('status'); $t->timestamps();
        });
        Schema::create('users', function (Blueprint $t) {
            $t->id(); $t->unsignedBigInteger('company_id'); $t->string('email');
            $t->string('password'); $t->boolean('is_active')->default(true);
            $t->boolean('two_factor_enabled')->default(false);
            $t->text('two_factor_secret')->nullable(); $t->text('two_factor_recovery_codes')->nullable();
            $t->string('active_web_session_id')->nullable(); $t->timestamp('active_login_at')->nullable();
            $t->timestamps();
        });
        Company::forceCreate(['id'=>1,'slug'=>'test-company','status'=>1]);
        User::forceCreate(['id'=>1,'company_id'=>1,'email'=>'test@example.com','password'=>'test-password']);
        $this->app['session']->start();
    }

    private function request(array $data=[]): Request
    {
        $request=Request::create('/company/test-company/login','POST',$data);
        $request->setLaravelSession($this->app['session.store']);
        return $request;
    }

    private function login()
    {
        return (new CompanyAuthController)->login($this->request([
            'email'=>'test@example.com','password'=>'test-password',
        ]),'test-company');
    }

    private function protectedPage()
    {
        return (new Company2FA)->handle($this->request(),fn()=>response('dashboard'));
    }

    public function test_back_and_repeated_login_cannot_skip_first_time_setup(): void
    {
        $this->app['session']->put('company_2fa_verified',true);
        $this->app['session']->put('company_2fa_secret','old-secret');
        $this->assertSame(route('company.2fa.setup','test-company'),$this->login()->getTargetUrl());
        $this->assertFalse(session('company_2fa_verified'));
        $this->assertNull(session('company_2fa_secret'));
        Route::shouldReceive('is')->with('company.login')->andReturn(true);
        $back=(new RedirectIfAuthenticated)->handle($this->request(),fn()=>response('login'));
        $this->assertSame('login',$back->getContent());
        $this->assertSame(route('company.2fa.setup','test-company'),$this->login()->getTargetUrl());
        $this->assertSame(route('company.2fa.setup','test-company'),$this->protectedPage()->getTargetUrl());
        // Even an old verified flag must not permit an account whose setup is incomplete.
        session(['company_2fa_verified'=>true]);
        $this->assertSame(route('company.2fa.setup','test-company'),$this->protectedPage()->getTargetUrl());
    }

    public function test_enabled_account_needs_otp_again_after_each_login(): void
    {
        User::findOrFail(1)->update(['two_factor_enabled'=>1,'two_factor_secret'=>(new Google2FA)->generateSecretKey()]);
        session(['company_2fa_verified'=>true]);
        $this->assertSame(route('company.2fa.challenge','test-company'),$this->login()->getTargetUrl());
        $this->assertSame(route('company.2fa.challenge','test-company'),$this->protectedPage()->getTargetUrl());
        Route::shouldReceive('is')->with('company.login')->andReturn(true);
        $back=(new RedirectIfAuthenticated)->handle($this->request(),fn()=>response('login'));
        $this->assertSame('login',$back->getContent());
        $otp=(new Google2FA)->getCurrentOtp(Auth::user()->two_factor_secret);
        (new CompanySecurityController)->verify($this->request(['otp'=>$otp]),'test-company');
        $this->assertSame('dashboard',$this->protectedPage()->getContent());
        $this->login();
        $this->assertSame(route('company.2fa.challenge','test-company'),$this->protectedPage()->getTargetUrl());
    }

    public function test_setup_requires_valid_otp_before_dashboard_access(): void
    {
        $this->login();
        $secret=(new Google2FA)->generateSecretKey();
        session(['company_2fa_secret'=>$secret]);
        $security=new CompanySecurityController;
        $otp=(new Google2FA)->getCurrentOtp($secret);
        $wrong=(new Google2FA)->getCurrentOtp((new Google2FA)->generateSecretKey());
        while ((new Google2FA)->verifyKey($secret,$wrong)) {
            $wrong=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        }
        $security->verify($this->request(['otp'=>$wrong]),'test-company');
        $this->assertFalse((bool)User::findOrFail(1)->two_factor_enabled);
        $this->assertSame(route('company.2fa.setup','test-company'),$this->protectedPage()->getTargetUrl());
        $security->verify($this->request(['otp'=>$otp]),'test-company');
        $this->assertTrue((bool)User::findOrFail(1)->two_factor_enabled);
        $this->assertSame('dashboard',$this->protectedPage()->getContent());
    }

    public function test_switch_from_pending_new_user_to_enrolled_user_uses_existing_authenticator(): void
    {
        $controller=new CompanyAuthController;
        $this->login();
        $secret=(new Google2FA)->generateSecretKey();
        session(['company_2fa_secret'=>$secret]);
        (new CompanySecurityController)->verify($this->request([
            'otp'=>(new Google2FA)->getCurrentOtp($secret),
        ]),'test-company');
        $controller->logout($this->request());
        User::forceCreate(['id'=>3,'company_id'=>1,'email'=>'user3@example.com','password'=>'third-password']);
        $third=$controller->login($this->request(['email'=>'user3@example.com','password'=>'third-password']),'test-company');
        $this->assertSame(route('company.2fa.setup','test-company'),$third->getTargetUrl());
        session(['company_2fa_secret'=>'pending-third-user-secret']);
        // Browser Back may restore the login form without a GET. POST must still process new credentials.
        $switched=(new RedirectIfAuthenticated)->handle($this->request(),fn()=>$this->login());
        $this->assertSame(route('company.2fa.challenge','test-company'),$switched->getTargetUrl());
        $this->assertSame(1,Auth::id());
        $this->assertSame($secret,Auth::user()->two_factor_secret);
        $this->assertNull(session('company_2fa_secret'));
        $this->assertFalse(session('company_2fa_verified'));
        $this->assertFalse((bool)User::findOrFail(3)->two_factor_enabled);
        $this->assertSame(route('company.2fa.challenge','test-company'),$this->protectedPage()->getTargetUrl());
        // A real GET of login during pending verification must also allow account switching.
        Route::shouldReceive('is')->with('company.login')->andReturn(true);
        $get=$this->request();$get->setMethod('GET');
        $response=(new RedirectIfAuthenticated)->handle($get,fn()=>response('login form'));
        $this->assertSame('login form',$response->getContent());
    }

    public function test_api_returns_setup_error_when_old_otp_is_submitted_after_reset(): void
    {
        $secret=(new Google2FA)->generateSecretKey();
        $user=User::findOrFail(1);
        $user->update(['two_factor_enabled'=>1,'two_factor_secret'=>$secret]);
        $oldOtp=(new Google2FA)->getCurrentOtp($secret);
        $user->update(['two_factor_enabled'=>0,'two_factor_secret'=>null]);
        $controller=new \App\Http\Controllers\Api\AuthController;
        $response=$controller->verifyOtp($this->request(['user_id'=>1,'otp'=>$oldOtp]),new \App\Services\OfficeAccessGuard);
        $this->assertSame(403,$response->getStatusCode());
        $this->assertSame('TWO_FACTOR_SETUP_REQUIRED',$response->getData(true)['code']);
        $this->assertTrue($response->getData(true)['setup_required']);
        $this->assertArrayNotHasKey('token',$response->getData(true));
        $login=$controller->login($this->request(['email'=>'test@example.com','password'=>'test-password']));
        $this->assertSame(403,$login->getStatusCode());
        $this->assertFalse($login->getData(true)['otp_required']);
        // Inconsistent legacy rows with enabled=true but no secret must also fail cleanly.
        $user->update(['two_factor_enabled'=>1]);
        $response=$controller->verifyOtp($this->request(['user_id'=>1,'otp'=>$oldOtp]),new \App\Services\OfficeAccessGuard);
        $this->assertSame('TWO_FACTOR_SETUP_REQUIRED',$response->getData(true)['code']);
    }
}
