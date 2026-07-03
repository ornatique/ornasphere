<?php

namespace App\Providers;

use App\Models\ApprovalHeader;
use App\Models\CompanyAppTheme;
use App\Models\Customer;
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
use App\Models\User;
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
        Sale::observe(CompanyActivityObserver::class);
        JobWorker::observe(CompanyActivityObserver::class);
        JobworkIssue::observe(CompanyActivityObserver::class);
        ProductionCost::observe(CompanyActivityObserver::class);
        LabourFormula::observe(CompanyActivityObserver::class);
        ProductionStep::observe(CompanyActivityObserver::class);
        VisitingCard::observe(CompanyActivityObserver::class);

        View::composer(['company_layout.header', 'company_layout.sidebar'], function ($view) {
            $view->with('companyNotificationSummary', CompanyNotificationService::summary(auth()->user()));
        });
    }
}
