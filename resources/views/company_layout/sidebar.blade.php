<nav class="sidebar sidebar-offcanvas" id="sidebar">
    @php
    $authUser = auth()->user();
    $company = optional($authUser)->company;
    $defaultCompanyAvatar = asset('celestial/assets/images/faces/face29.png');
    $companyLogo = !empty(optional($company)->company_logo)
        ? asset('public/' . ltrim($company->company_logo, '/'))
        : $defaultCompanyAvatar;
    $isCompanyAdmin = $authUser && $authUser->hasRole('company_admin');
    $canNotifications = $authUser && ($isCompanyAdmin || $authUser->can('notification-view'));
    $routeName = (string) optional(request()->route())->getName();
    $isVisitingCardsRoute = str_starts_with($routeName, 'company.reports.visiting-cards.');
    $isSalesRoute = str_starts_with($routeName, 'company.sales.')
    || str_starts_with($routeName, 'company.returns.')
    || str_starts_with($routeName, 'company.approval.');
    $isReportRoute = str_starts_with($routeName, 'company.reports.') && !$isVisitingCardsRoute;

    $canModule = function (string $module, string $action = 'view') use ($authUser, $isCompanyAdmin) {
    if ($isCompanyAdmin) {
    return true;
    }

    if (!$authUser) {
    return false;
    }

    $moduleVariants = array_unique([
    $module,
    str_replace('-', '', $module),
    str_replace('-', '_', $module),
    str_replace('-', '.', $module),
    str_replace('-', ' ', $module),
    ]);

    $candidates = [];
    foreach ($moduleVariants as $m) {
    $candidates[] = "{$m}-{$action}";
    $candidates[] = "{$m}.{$action}";
    $candidates[] = "{$m}_{$action}";
    $candidates[] = "{$m} {$action}";
    $candidates[] = "{$action}-{$m}";
    $candidates[] = "{$action}.{$m}";
    $candidates[] = "{$action}_{$m}";
    $candidates[] = "{$action} {$m}";
    }

    return $authUser->hasAnyPermission(array_values(array_unique($candidates)));
    };

    $canUsers = $canModule('user');
    $canCustomers = $canModule('customer');
    $canJobWorker = $canModule('job-worker');
    $canJobworkIssueEntry = $canModule('jobwork-issue');
    $canRoles = $canModule('role');
    $canPermissions = $canModule('permission');
    $canAppTheme = $canModule('app-theme');

    $canItems = $canModule('item');
    $canItemSets = $canModule('item-set');
    $canLabelConfig = $canModule('label-config');
    $canLabelPrint = $canModule('label-print');
    $canOtherCharge = $canModule('other-charge');
    $canProductionCost = $canModule('production-cost');
    $canLabourFormula = $canModule('labour-formula');
    $canProductionStep = $canModule('production-step');

    $canApproval = $canModule('approval');
    $canSales = $canModule('sale');
    $canSaleAdvance = $canModule('sale-advance');
    $canApprovalReturn = $canModule('approval-return');
    $canReportSalesSummary = $canModule('report-sales-summary');
    $canReportPurchaseReceiverSummary = $canModule('report-purchase-receiver-summary');
    $canReportStockPosition = $canModule('report-stock-position');
    $canReportApprovalOutstanding = $canModule('report-approval-outstanding');
    $canReportOutstandingAmount = $canModule('report-outstanding-amount');
    $canReportBarcodeHistory = $canModule('report-barcode-history');
    $canReportVisitingCards = $canModule('report-visiting-cards');
    $notificationModules = (array) data_get($companyNotificationSummary ?? [], 'modules', []);
    $notifyCount = fn (string $module): int => (int) ($notificationModules[$module] ?? 0);
    $notifySum = fn (array $modules): int => array_sum(array_map(fn ($module) => (int) ($notificationModules[$module] ?? 0), $modules));
    @endphp

    <style>
        .sidebar-notification-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 18px;
            height: 18px;
            margin-left: 8px;
            padding: 0 6px;
            border-radius: 999px;
            background: #ff1744;
            color: #fff;
            font-size: 10px;
            font-weight: 700;
            line-height: 1;
        }
    </style>

    <ul class="nav" id="sidebar-accordion">

        {{-- PROFILE --}}
        <li class="nav-item">
            <div class="d-flex sidebar-profile">
                <div class="sidebar-profile-image">
                    <img src="{{ $companyLogo }}" alt="company logo" onerror="this.onerror=null;this.src='{{ $defaultCompanyAvatar }}';">
                    <span class="sidebar-status-indicator"></span>
                </div>
                <div class="sidebar-profile-name">
                    <p class="sidebar-name">{{ auth()->user()->name }}</p>
                    <p class="sidebar-designation">Welcome</p>
                </div>
            </div>
        </li>

        {{-- DASHBOARD --}}
        <li class="nav-item {{ request()->routeIs('company.dashboard') ? 'active' : '' }}">
            <a class="nav-link"
                href="{{ route('company.dashboard', auth()->user()->company->slug) }}">
                <i class="typcn typcn-device-desktop menu-icon"></i>
                <span class="menu-title">Dashboard</span>
            </a>
        </li>

        @if($canAppTheme)
        <li class="nav-item {{ request()->routeIs('company.app-themes.*') ? 'active' : '' }}">
            <a class="nav-link"
                href="{{ route('company.app-themes.index', auth()->user()->company->slug) }}">
                <i class="typcn typcn-brush menu-icon"></i>
                <span class="menu-title">App Theme</span>
                @if($canNotifications && $notifyCount('app_theme') > 0)
                <span class="sidebar-notification-badge">{{ $notifyCount('app_theme') > 99 ? '99+' : $notifyCount('app_theme') }}</span>
                @endif
            </a>
        </li>
        @endif

        {{-- ================= USERS MANAGEMENT ================= --}}
        @php
        $userManagementActive =
        request()->routeIs('company.roles.*') ||
        request()->routeIs('company.permissions.*') ||
        request()->routeIs('company.customers.*') ||
        request()->routeIs('company.users.*');
        @endphp
        @if($canUsers || $canCustomers || $canRoles || $canPermissions)
        <li class="nav-item {{ $userManagementActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#user-management"
                aria-expanded="{{ $userManagementActive ? 'true' : 'false' }}">
                <i class="typcn typcn-user menu-icon"></i>
                <span class="menu-title">User Management</span>
                @php $userManagementNotifications = $notifySum(['user', 'customer']); @endphp
                @if($canNotifications && $userManagementNotifications > 0)
                <span class="sidebar-notification-badge">{{ $userManagementNotifications > 99 ? '99+' : $userManagementNotifications }}</span>
                @endif
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $userManagementActive ? 'show' : '' }}" id="user-management" data-bs-parent="#sidebar-accordion">
                <ul class="nav flex-column sub-menu">
                    @if($canUsers)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.users.*') ? 'active' : '' }}"
                            href="{{ route('company.users.index', auth()->user()->company->slug) }}">
                            Users
                        </a>
                    </li>
                    @endif

                    @if($canCustomers)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.customers.*') ? 'active' : '' }}"
                            href="{{ route('company.customers.index', auth()->user()->company->slug) }}">
                            Customers
                            @if($canNotifications && $notifyCount('customer') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('customer') > 99 ? '99+' : $notifyCount('customer') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canRoles)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.roles.*') ? 'active' : '' }}"
                            href="{{ route('company.roles.index', auth()->user()->company->slug) }}">
                            Roles
                        </a>
                    </li>
                    @endif

                    @if($canPermissions)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.permissions.*') ? 'active' : '' }}"
                            href="{{ route('company.permissions.index', auth()->user()->company->slug) }}">
                            Permissions
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

        {{-- ================= ITEM MANAGEMENT ================= --}}
        @php
        $currentRouteName = optional(request()->route())->getName();

        $labelItemsRoutes = [
        'company.list_itemset',
        'company.item_sets.index',
        'company.item_sets.saveCell',
        'company.item_sets.load',
        'company.item_sets.finalize',
        'company.item_sets.finalize.get',
        ];

        $labelPrintingRoutes = [
        'company.item_sets.qrList',
        'company.item_sets.printPdf',
        'company.item_sets.qrImage',
        ];

        $labelItemsActive = in_array($currentRouteName, $labelItemsRoutes, true);
        $labelPrintingActive = in_array($currentRouteName, $labelPrintingRoutes, true);

        $itemActive =
        (
        request()->routeIs('company.items.*')
        ) ||
        request()->routeIs('company.label_config.*') ||
        $labelItemsActive ||
        $labelPrintingActive ||
        request()->routeIs('company.other-charge.*');
        @endphp
        @if($canItems || $canItemSets || $canLabelConfig || $canLabelPrint || $canOtherCharge)
        <li class="nav-item {{ $itemActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#item-management"
                aria-expanded="{{ $itemActive ? 'true' : 'false' }}">
                <i class="typcn typcn-shopping-bag menu-icon"></i>
                <span class="menu-title">Item Management</span>
                @php $itemManagementNotifications = $notifySum(['item', 'label_config', 'item_set', 'other_charge']); @endphp
                @if($canNotifications && $itemManagementNotifications > 0)
                <span class="sidebar-notification-badge">{{ $itemManagementNotifications > 99 ? '99+' : $itemManagementNotifications }}</span>
                @endif
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $itemActive ? 'show' : '' }}" id="item-management" data-bs-parent="#sidebar-accordion">
                <ul class="nav flex-column sub-menu">
                    @if($canItems)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.items.*') ? 'active' : '' }}"
                            href="{{ route('company.items.index', auth()->user()->company->slug) }}">
                            Items List
                            @if($canNotifications && $notifyCount('item') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('item') > 99 ? '99+' : $notifyCount('item') }}</span>
                            @endif
                        </a>
                    </li>

                    @endif

                    @if($canLabelConfig)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.label_config.*') ? 'active' : '' }}"
                            href="{{ route('company.label_config.index', auth()->user()->company->slug) }}">
                            Label Config
                            @if($canNotifications && $notifyCount('label_config') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('label_config') > 99 ? '99+' : $notifyCount('label_config') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif


                    @if($canItemSets)
                    <li class="nav-item">
                        <a class="nav-link {{ $labelItemsActive ? 'active' : '' }}"
                            href="{{ route('company.list_itemset', auth()->user()->company->slug) }}">
                            Label Items
                            @if($canNotifications && $notifyCount('item_set') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('item_set') > 99 ? '99+' : $notifyCount('item_set') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif



                    @if($canLabelPrint)
                    <li class="nav-item">
                        <a class="nav-link {{ $labelPrintingActive ? 'active' : '' }}"
                            href="{{ route('company.item_sets.qrList', auth()->user()->company->slug) }}">
                            Label Printing
                        </a>
                    </li>
                    @endif

                    @if($canOtherCharge)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.other-charge.*') ? 'active' : '' }}"
                            href="{{ route('company.other-charge.index', auth()->user()->company->slug) }}">
                            Other Charges
                            @if($canNotifications && $notifyCount('other_charge') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('other_charge') > 99 ? '99+' : $notifyCount('other_charge') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

        {{-- ================= JOBWORK ISSUE ================= --}}
        @php
        $jobworkIssueActive =
        request()->routeIs('company.jobwork-issue.*') ||
        request()->routeIs('company.job-workers.*') ||
        request()->routeIs('company.production-cost.*') ||
        request()->routeIs('company.labour-formula.*') ||
        request()->routeIs('company.production-step.*');
        @endphp
        @if($canJobworkIssueEntry || $canJobWorker || $canProductionCost || $canLabourFormula || $canProductionStep)
        <li class="nav-item {{ $jobworkIssueActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#jobwork-issue-menu"
                aria-expanded="{{ $jobworkIssueActive ? 'true' : 'false' }}">
                <i class="typcn typcn-briefcase menu-icon"></i>
                <span class="menu-title">Jobwork Issue</span>
                @php $jobworkNotifications = $notifySum(['labour_formula', 'production_cost', 'production_step', 'job_worker', 'jobwork_issue']); @endphp
                @if($canNotifications && $jobworkNotifications > 0)
                <span class="sidebar-notification-badge">{{ $jobworkNotifications > 99 ? '99+' : $jobworkNotifications }}</span>
                @endif
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $jobworkIssueActive ? 'show' : '' }}" id="jobwork-issue-menu" data-bs-parent="#sidebar-accordion">
                <ul class="nav flex-column sub-menu">

                    @if($canLabourFormula)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.labour-formula.*') ? 'active' : '' }}"
                            href="{{ route('company.labour-formula.index', auth()->user()->company->slug) }}">
                            Labour Formula
                            @if($canNotifications && $notifyCount('labour_formula') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('labour_formula') > 99 ? '99+' : $notifyCount('labour_formula') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canProductionCost)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.production-cost.*') ? 'active' : '' }}"
                            href="{{ route('company.production-cost.index', auth()->user()->company->slug) }}">
                            Production Cost
                            @if($canNotifications && $notifyCount('production_cost') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('production_cost') > 99 ? '99+' : $notifyCount('production_cost') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canProductionStep)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.production-step.*') ? 'active' : '' }}"
                            href="{{ route('company.production-step.index', auth()->user()->company->slug) }}">
                            Production Step
                            @if($canNotifications && $notifyCount('production_step') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('production_step') > 99 ? '99+' : $notifyCount('production_step') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canJobWorker)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.job-workers.*') ? 'active' : '' }}"
                            href="{{ route('company.job-workers.index', auth()->user()->company->slug) }}">
                            Job Worker
                            @if($canNotifications && $notifyCount('job_worker') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('job_worker') > 99 ? '99+' : $notifyCount('job_worker') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canJobworkIssueEntry)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.jobwork-issue.*') ? 'active' : '' }}"
                            href="{{ route('company.jobwork-issue.index', auth()->user()->company->slug) }}">
                            Jobwork Issue
                            @if($canNotifications && $notifyCount('jobwork_issue') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('jobwork_issue') > 99 ? '99+' : $notifyCount('jobwork_issue') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif
                </ul>
            </div>
        </li>
        @endif

        {{-- ================= SALES ================= --}}
        @php
        $salesActive = $isSalesRoute;
        $salesEstimateActive =
            str_starts_with($routeName, 'company.sales.')
            && !str_starts_with($routeName, 'company.sales.advance.');
        $salesAdvanceActive = str_starts_with($routeName, 'company.sales.advance.');
        @endphp
        @if($canApproval || $canSales || $canSaleAdvance || $canApprovalReturn)
        <li class="nav-item {{ $salesActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#sales-menu"
                aria-expanded="{{ $salesActive ? 'true' : 'false' }}">
                <i class="typcn typcn-chart-line menu-icon"></i>
                <span class="menu-title">Sales</span>
                @php $salesNotifications = $notifySum(['approval', 'approval_return', 'sale']); @endphp
                @if($canNotifications && $salesNotifications > 0)
                <span class="sidebar-notification-badge">{{ $salesNotifications > 99 ? '99+' : $salesNotifications }}</span>
                @endif
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $salesActive ? 'show' : '' }}" id="sales-menu">
                <ul class="nav flex-column sub-menu">
                    @if($canApproval)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.approval.*') ? 'active' : '' }}"
                            href="{{ route('company.approval.index', auth()->user()->company->slug) }}">
                            Approval
                            @if($canNotifications && $notifyCount('approval') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('approval') > 99 ? '99+' : $notifyCount('approval') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canApprovalReturn)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.returns.*') ? 'active' : '' }}"
                            href="{{ route('company.returns.index', auth()->user()->company->slug) }}">
                            Approval Return
                            @if($canNotifications && $notifyCount('approval_return') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('approval_return') > 99 ? '99+' : $notifyCount('approval_return') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif

                    @if($canSales)
                    <li class="nav-item">
                        <a class="nav-link {{ $salesEstimateActive ? 'active' : '' }}"
                            href="{{ route('company.sales.index', auth()->user()->company->slug) }}">
                            Sales Estimate
                            @if($canNotifications && $notifyCount('sale') > 0)
                            <span class="sidebar-notification-badge">{{ $notifyCount('sale') > 99 ? '99+' : $notifyCount('sale') }}</span>
                            @endif
                        </a>
                    </li>
                    @endif
                    @if($canSaleAdvance)
                    <li class="nav-item">
                        <a class="nav-link {{ $salesAdvanceActive ? 'active' : '' }}"
                            href="{{ route('company.sales.advance.index', auth()->user()->company->slug) }}">
                            Receive / Return / Purchase
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

        {{-- ================= REPORTS ================= --}}
        @php $reportActive = $isReportRoute; @endphp
        @if($canReportSalesSummary || $canReportPurchaseReceiverSummary || $canReportStockPosition || $canReportApprovalOutstanding || $canReportOutstandingAmount || $canReportBarcodeHistory)
        <li class="nav-item {{ $reportActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#reports-menu"
                aria-expanded="{{ $reportActive ? 'true' : 'false' }}">
                <i class="typcn typcn-document-text menu-icon"></i>
                <span class="menu-title">Reports</span>
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $reportActive ? 'show' : '' }}" id="reports-menu">
                <ul class="nav flex-column sub-menu">
                    @if($canReportSalesSummary)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.sales-summary.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.sales-summary.index', auth()->user()->company->slug) }}">
                            Sales Summary
                        </a>
                    </li>
                    @endif

                    @if($canReportPurchaseReceiverSummary)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.purchase-receiver-summary.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.purchase-receiver-summary.index', auth()->user()->company->slug) }}">
                            Purchase / Receiver Summary
                        </a>
                    </li>
                    @endif

                    @if($canReportStockPosition)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.stock-position.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.stock-position.index', auth()->user()->company->slug) }}">
                            Stock Position
                        </a>
                    </li>
                    @endif

                    @if($canReportApprovalOutstanding)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.approval-outstanding.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.approval-outstanding.index', auth()->user()->company->slug) }}">
                            Approval Outstanding
                        </a>
                    </li>
                    @endif

                    @if($canReportOutstandingAmount)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.outstanding-amount.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.outstanding-amount.index', auth()->user()->company->slug) }}">
                            Outstanding Amount
                        </a>
                    </li>
                    @endif

                    @if($canReportBarcodeHistory)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.reports.barcode-history.*') ? 'active' : '' }}"
                            href="{{ route('company.reports.barcode-history.index', auth()->user()->company->slug) }}">
                            Barcode History
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

        {{-- ================= VISITING CARDS ================= --}}
        @if($canReportVisitingCards)
        <li class="nav-item {{ $isVisitingCardsRoute ? 'active' : '' }}">
            <a class="nav-link"
                href="{{ route('company.reports.visiting-cards.index', auth()->user()->company->slug) }}">
                <i class="typcn typcn-contacts menu-icon"></i>
                <span class="menu-title">Visiting Cards</span>
                @if($canNotifications && $notifyCount('visiting_card') > 0)
                <span class="sidebar-notification-badge">{{ $notifyCount('visiting_card') > 99 ? '99+' : $notifyCount('visiting_card') }}</span>
                @endif
            </a>
        </li>
        @endif

    </ul>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const salesToggle = document.querySelector('a[href="#sales-menu"]');
            const reportsToggle = document.querySelector('a[href="#reports-menu"]');
            const salesMenu = document.getElementById('sales-menu');
            const reportsMenu = document.getElementById('reports-menu');

            if (!window.bootstrap || !salesMenu || !reportsMenu || !salesToggle || !reportsToggle) {
                return;
            }

            const salesCollapse = window.bootstrap.Collapse.getOrCreateInstance(salesMenu, {
                toggle: false
            });
            const reportsCollapse = window.bootstrap.Collapse.getOrCreateInstance(reportsMenu, {
                toggle: false
            });

            salesToggle.addEventListener('click', function() {
                if (reportsMenu.classList.contains('show')) {
                    reportsCollapse.hide();
                }
            });

            reportsToggle.addEventListener('click', function() {
                if (salesMenu.classList.contains('show')) {
                    salesCollapse.hide();
                }
            });
        });
    </script>
</nav>
