<?php

namespace App\Providers;

use App\Models\ApprovalHeader;
use App\Models\CompanyAppTheme;
use App\Models\Customer;
use App\Models\CustomerAdvanceLedger;
use App\Models\Item;
use App\Models\ItemSet;
use App\Models\JobWorker;
use App\Models\JobworkIssue;
use App\Models\LabelConfig;
use App\Models\LabourFormula;
use App\Models\OtherCharge;
use App\Models\ProductionCost;
use App\Models\ProductionStep;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\User;
use App\Models\CastingHeatingItem;
use App\Models\CastingMetalIssueItem;
use App\Models\CastingReleaseItem;
use App\Models\CastingSortingItem;
use App\Models\TreeCuttingIssueItem;
use App\Models\TreeCuttingReceiveItem;
use App\Models\VacuumBuch;
use App\Models\VacuumProcess;
use App\Models\VacuumVoucher;
use App\Models\VisitingCard;
use App\Observers\CompanyActivityObserver;
use App\Services\CompanyNotificationService;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        User::observe(CompanyActivityObserver::class);
        Customer::observe(CompanyActivityObserver::class);
        CompanyAppTheme::observe(CompanyActivityObserver::class);
        Item::observe(CompanyActivityObserver::class);
        LabelConfig::observe(CompanyActivityObserver::class);
        ItemSet::observe(CompanyActivityObserver::class);
        OtherCharge::observe(CompanyActivityObserver::class);
        ApprovalHeader::observe(CompanyActivityObserver::class);
        SaleReturn::observe(CompanyActivityObserver::class);
        Sale::observe(CompanyActivityObserver::class);
        CustomerAdvanceLedger::observe(CompanyActivityObserver::class);
        JobWorker::observe(CompanyActivityObserver::class);
        JobworkIssue::observe(CompanyActivityObserver::class);
        ProductionCost::observe(CompanyActivityObserver::class);
        LabourFormula::observe(CompanyActivityObserver::class);
        ProductionStep::observe(CompanyActivityObserver::class);
        VisitingCard::observe(CompanyActivityObserver::class);
        VacuumBuch::observe(CompanyActivityObserver::class);
        VacuumProcess::observe(CompanyActivityObserver::class);
        VacuumVoucher::observe(CompanyActivityObserver::class);
        CastingHeatingItem::observe(CompanyActivityObserver::class);
        CastingMetalIssueItem::observe(CompanyActivityObserver::class);
        CastingReleaseItem::observe(CompanyActivityObserver::class);
        TreeCuttingIssueItem::observe(CompanyActivityObserver::class);
        TreeCuttingReceiveItem::observe(CompanyActivityObserver::class);
        CastingSortingItem::observe(CompanyActivityObserver::class);

        View::composer(['company_layout.header', 'company_layout.sidebar'], function ($view) {
            $view->with('companyNotificationSummary', CompanyNotificationService::summary(auth()->user()));
        });
    }
}
