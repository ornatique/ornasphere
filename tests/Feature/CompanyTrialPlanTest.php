<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Models\Company;
use App\Services\CompanyPlanService;
class CompanyTrialPlanTest extends TestCase
{
    public function test_trial_warning_and_exact_expiry(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        $c=new Company(['plan'=>'trial','plan_started_at'=>now(),'plan_expires_at'=>now()->addDays(10)]);
        $this->assertFalse(CompanyPlanService::status($c)['alert']['show_popup']);
        $this->travel(7)->days();
        $s=CompanyPlanService::status($c); $this->assertSame(3,$s['days_remaining']); $this->assertTrue($s['alert']['show_popup']); $this->assertFalse($s['expired']);
        $this->travel(3)->days();
        $s=CompanyPlanService::status($c); $this->assertTrue($s['expired']); $this->assertSame('TRIAL_EXPIRED',$s['code']);
    }
    public function test_running_api_session_is_blocked_at_expiry(): void {
        $c=new Company(['id'=>1,'status'=>1,'plan'=>'trial','plan_started_at'=>now()->subDays(10),'plan_expires_at'=>now()]);
        $u=new \App\Models\User(['company_id'=>1,'is_active'=>1]); $u->setRelation('company',$c);
        $r=\Illuminate\Http\Request::create('/api/customers','GET'); $r->setUserResolver(fn()=>$u);
        $response=(new \App\Http\Middleware\EnsureCompanyIsActive)->handle($r, function () { $this->fail('Expired request reached controller'); });
        $this->assertSame(403,$response->getStatusCode());
        $this->assertSame('TRIAL_EXPIRED',$response->getData(true)['code']);
        $this->assertSame(1,$u->is_active);
    }
    public function test_countdown_is_server_timed_and_hidden_for_paid_plans(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        $c=new Company(['plan'=>'trial','plan_started_at'=>now(),'plan_expires_at'=>now()->addDays(2)->addHours(4)]);
        $timer=CompanyPlanService::status($c)['trial_countdown'];
        $this->assertTrue($timer['show']); $this->assertTrue($timer['warning']);
        $this->assertSame(187200,$timer['remaining_seconds']);
        $this->assertSame(now()->toIso8601String(),$timer['server_time']);
        $c->plan='yearly'; $this->assertFalse(CompanyPlanService::status($c)['trial_countdown']['show']);
        $c->plan='trial'; $this->travel(3)->days();
        $timer=CompanyPlanService::status($c)['trial_countdown'];
        $this->assertSame(0,$timer['remaining_seconds']); $this->assertFalse($timer['show']);
    }
    public function test_paid_warning_and_trial_fallback(): void {
        $this->travelTo(\Carbon\Carbon::parse('2026-10-22 12:00:00'));
        $c=new Company(['plan'=>'yearly','plan_started_at'=>now(),'plan_expires_at'=>now()->addDays(30)]);
        $this->assertTrue(CompanyPlanService::status($c)['alert']['show_popup']);
        $trial=new Company(['plan'=>'trial','plan_started_at'=>now()]);
        $this->assertSame(10,CompanyPlanService::status($trial)['days_remaining']);
        $this->assertFalse(CompanyPlanService::status($trial)['expired']);
    }
}
