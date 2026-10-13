<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CompanyNotificationService;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyNotificationPaginationTest extends TestCase
{
    public function test_cursor_reaches_all_unread_items_without_other_company_or_duplicates(): void
    {
        DB::statement('CREATE TABLE users (id integer primary key, name text)');
        DB::statement('CREATE TABLE company_activity_notifications (id integer primary key, company_id integer, actor_user_id integer, module text, action text, subject_type text, subject_id integer, message text, title text, read_at text, created_at text)');
        for ($id=1; $id<=1091; $id++) {
            DB::table('company_activity_notifications')->insert(['id'=>$id,'company_id'=>1,'module'=>'sale','action'=>'created','subject_id'=>$id,'message'=>'Activity '.$id,'created_at'=>'2026-10-09 10:00:00']);
        }
        DB::table('company_activity_notifications')->insert(['id'=>2000,'company_id'=>2,'module'=>'sale','message'=>'Foreign']);
        DB::table('company_activity_notifications')->insert(['id'=>2001,'company_id'=>1,'module'=>'sale','message'=>'Read','read_at'=>'2026-10-09']);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->company_id = 1;
        $user->shouldReceive('hasRole')->with('company_admin')->andReturn(true);
        $cursor = null;
        $seen = [];
        do {
            $batch = CompanyNotificationService::latest($user,21,true,$cursor);
            $more = $batch->count() > 20;
            $page = $batch->take(20);
            $seen = array_merge($seen,$page->pluck('id')->all());
            $cursor = $page->last()?->id;
        } while ($more);
        $this->assertCount(1091,$seen);
        $this->assertSame(range(1091,1),$seen);
        $this->assertCount(1091,array_unique($seen));
        $this->assertCount(0,CompanyNotificationService::latest(null));
    }
}
