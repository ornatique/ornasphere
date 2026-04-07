<nav class="sidebar sidebar-offcanvas" id="sidebar">
    @php
    $authUser = auth()->user();
    $isCompanyAdmin = $authUser && $authUser->hasRole('company_admin');

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
            $candidates[] = "{$m}-manage";
            $candidates[] = "{$m}.manage";
            $candidates[] = "{$m}_manage";
            $candidates[] = "{$m} manage";
            $candidates[] = "manage-{$m}";
            $candidates[] = "manage.{$m}";
            $candidates[] = "manage_{$m}";
            $candidates[] = "manage {$m}";
        }

        return $authUser->hasAnyPermission(array_values(array_unique($candidates)));
    };

    $canUsers = $canModule('user');
    $canCustomers = $canModule('customer');
    $canRoles = $canModule('role');
    $canPermissions = $canModule('permission');

    $canItems = $canModule('item');
    $canItemSets = $canModule('item-set');
    $canLabelConfig = $canModule('label-config');
    $canLabelPrint = $canModule('label-print');
    $canOtherCharge = $canModule('other-charge');

    $canApproval = $canModule('approval');
    $canSales = $canModule('sale');
    $canReturns = $canModule('return');
    @endphp

    <ul class="nav">

        {{-- PROFILE --}}
        <li class="nav-item">
            <div class="d-flex sidebar-profile">
                <div class="sidebar-profile-image">
                    <img src="{{ asset('celestial/assets/images/faces/face29.png') }}" alt="image">
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
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $userManagementActive ? 'show' : '' }}" id="user-management">
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
        $itemActive =
        request()->routeIs('company.items.*') ||
        request()->routeIs('company.label_config.*') ||
        request()->routeIs('company.item_sets.*') ||
        request()->routeIs('company.other-charge.*') ||
        request()->routeIs('company.item_sets.qrList.*');
        @endphp
        @if($canItems || $canItemSets || $canLabelConfig || $canLabelPrint || $canOtherCharge)
        <li class="nav-item {{ $itemActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#item-management"
                aria-expanded="{{ $itemActive ? 'true' : 'false' }}">
                <i class="typcn typcn-shopping-bag menu-icon"></i>
                <span class="menu-title">Item Management</span>
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $itemActive ? 'show' : '' }}" id="item-management">
                <ul class="nav flex-column sub-menu">
                    @if($canItems)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.items.*') ? 'active' : '' }}"
                            href="{{ route('company.items.index', auth()->user()->company->slug) }}">
                            Items List
                        </a>
                    </li>
                    @endif

                    @if($canItemSets)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.list_itemset') ? 'active' : '' }}"
                            href="{{ route('company.list_itemset', auth()->user()->company->slug) }}">
                            Label Items
                        </a>
                    </li>
                    @endif

                    @if($canLabelConfig)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.label_config.*') ? 'active' : '' }}"
                            href="{{ route('company.label_config.index', auth()->user()->company->slug) }}">
                            Label Config
                        </a>
                    </li>
                    @endif

                    @if($canLabelPrint)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.item_sets.qrList') ? 'active' : '' }}"
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
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

        {{-- ================= SALES ================= --}}
        @php
        $salesActive = request()->routeIs('company.sales.*')
        || request()->routeIs('company.returns.*')
        || request()->routeIs('company.approval.*');
        @endphp
        @if($canApproval || $canSales || $canReturns)
        <li class="nav-item {{ $salesActive ? 'active' : '' }}">
            <a class="nav-link"
                data-bs-toggle="collapse"
                href="#sales-menu"
                aria-expanded="{{ $salesActive ? 'true' : 'false' }}">
                <i class="typcn typcn-chart-line menu-icon"></i>
                <span class="menu-title">Sales</span>
                <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>

            <div class="collapse {{ $salesActive ? 'show' : '' }}" id="sales-menu">
                <ul class="nav flex-column sub-menu">
                    @if($canApproval)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.approval.*') ? 'active' : '' }}"
                            href="{{ route('company.approval.index', auth()->user()->company->slug) }}">
                            Approval
                        </a>
                    </li>
                    @endif

                    @if($canSales)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.sales.*') ? 'active' : '' }}"
                            href="{{ route('company.sales.index', auth()->user()->company->slug) }}">
                            Sales Estimate
                        </a>
                    </li>
                    @endif

                    @if($canReturns)
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.returns.*') ? 'active' : '' }}"
                            href="{{ route('company.returns.index', auth()->user()->company->slug) }}">
                            Purchase / Receiver
                        </a>
                    </li>
                    @endif

                </ul>
            </div>
        </li>
        @endif

    </ul>
</nav>
