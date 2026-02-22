<nav class="sidebar sidebar-offcanvas" id="sidebar">
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
                request()->routeIs('company.users.*');
        @endphp

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

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.users.*') ? 'active' : '' }}"
                            href="{{ route('company.users.index', auth()->user()->company->slug) }}">
                            Users
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.roles.*') ? 'active' : '' }}"
                            href="{{ route('company.roles.index', auth()->user()->company->slug) }}">
                            Roles
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.permissions.*') ? 'active' : '' }}"
                            href="{{ route('company.permissions.index', auth()->user()->company->slug) }}">
                            Permissions
                        </a>
                    </li>

                </ul>
            </div>
        </li>

        {{-- ================= ITEM MANAGEMENT ================= --}}
        @php
            $itemActive =
                request()->routeIs('company.items.*') ||
                request()->routeIs('company.label_config.*') ||
                request()->routeIs('company.item_sets.*') ||
                request()->routeIs('company.other-charge.*');
        @endphp

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

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.items.*') ? 'active' : '' }}"
                            href="{{ route('company.items.index', auth()->user()->company->slug) }}">
                            Items List
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.item_sets.index') ? 'active' : '' }}"
                            href="{{ route('company.item_sets.index', auth()->user()->company->slug) }}">
                            Label Items
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.label_config.*') ? 'active' : '' }}"
                            href="{{ route('company.label_config.index', auth()->user()->company->slug) }}">
                            Label Config
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.item_sets.qrList') ? 'active' : '' }}"
                            href="{{ route('company.item_sets.qrList', auth()->user()->company->slug) }}">
                            Label Printing
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.other-charge.*') ? 'active' : '' }}"
                            href="{{ route('company.other-charge.index', auth()->user()->company->slug) }}">
                            Other Charges
                        </a>
                    </li>

                </ul>
            </div>
        </li>

        {{-- ================= SALES ================= --}}
        @php
            $salesActive = request()->routeIs('company.sales.*');
        @endphp

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

                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('company.sales.index') ? 'active' : '' }}"
                            href="{{ route('company.sales.index', auth()->user()->company->slug) }}">
                            Sales Estimate
                        </a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link "
                            href="#">
                            Purchase / Reciver
                        </a>
                    </li>

                </ul>
            </div>
        </li>

    </ul>
</nav>