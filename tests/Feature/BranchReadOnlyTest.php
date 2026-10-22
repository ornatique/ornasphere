<?php
namespace Tests\Feature;
use Tests\TestCase;
use App\Services\BranchReadOnly;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
class BranchReadOnlyTest extends TestCase
{
    public function test_foreign_actions_disabled_while_own_actions_remain(): void
    {
        $html='<a href="/company/child/roles/1/edit">Edit</a><button class="deleteBtn" data-url="/delete">Delete</button><a href="/pdf">PDF</a>';
        $data=BranchReadOnly::protectRows(['data'=>[['company_id'=>1,'action'=>$html],['company_id'=>2,'action'=>$html]]],(object)['company_id'=>1]);
        $this->assertSame($html,$data['data'][0]['action']);
        $this->assertFalse($data['data'][1]['can_manage']);
        $this->assertStringContainsString('disabled',$data['data'][1]['action']);
        $this->assertStringNotContainsString('href="/company/child/roles/1/edit"',$data['data'][1]['action']);
        $this->assertStringContainsString('href="/pdf"',$data['data'][1]['action']);
        $this->assertStringNotContainsString('class="deleteBtn"',$data['data'][1]['action']);
    }
    public function test_edit_and_writes_are_not_read_requests(): void
    {
        foreach ([['GET','edit',false],['POST','update',false],['DELETE','destroy',false],['GET','index',true],['GET','pdf',true]] as [$verb,$action,$expected]) {
            $request=Request::create('/test',$verb);
            $route=new Route([$verb],'/test',['uses'=>'App\\Http\\Controllers\\Company\\CompanyRoleController@'.$action]);
            $request->setRouteResolver(fn ()=>$route);
            $this->assertSame($expected,BranchReadOnly::isReadRequest($request));
        }
        $this->assertFalse(BranchReadOnly::canWrite((object)['company_id'=>1],2));
        $this->assertSame(403,BranchReadOnly::denied()->getStatusCode());
    }
}
