<?php
namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Blade;
use App\Models\User;

class DashboardBladeInitializationTest extends TestCase
{
    public function test_rates_initialize_after_opening_balance_section(): void
    {
        $user = new User;
        $user->setRelation('company', (object)['parent_company_id'=>1]);
        $this->actingAs($user);
        $source = file_get_contents(resource_path('views/company/dashboard.blade.php'));
        $start = strpos($source, '    @php', strpos($source, '@if($canViewDashboardData'));
        $end = strpos($source, '    <style>', $start);
        $fragment = substr($source, $start, $end - $start);
        $output = Blade::render($fragment.'{{ $marketRates->count() }}|{{ $spotRates->count() }}', [
            'metalRates'=>['rates'=>[['symbol'=>'GOLD'],['symbol'=>'SLV 999']]],
        ]);
        $this->assertStringContainsString('1|1', $output);
        $empty = Blade::render($fragment.'{{ $marketRates->count() }}|{{ $spotRates->count() }}');
        $this->assertStringContainsString('0|0', $empty);
    }
}
