 <nav class="sidebar sidebar-offcanvas" id="sidebar">
   <ul class="nav">
     <li class="nav-item">
       <div class="d-flex sidebar-profile">
         <div class="sidebar-profile-image">
           <img src="{{ asset('celestial/assets/images/faces/face29.png') }}" alt="image">
           <span class="sidebar-status-indicator"></span>
         </div>
         <div class="sidebar-profile-name">
           <p class="sidebar-name">
             {{ auth()->user()->name }}
           </p>
           <p class="sidebar-designation">
             Welcome
           </p>
         </div>
       </div>
     </li>
     <li class="nav-item {{ request()->routeIs('company.dashboard') ? 'active' : '' }}">
       <a class="nav-link"
         href="{{ route('company.dashboard', auth()->user()->company->slug) }}">
         <i class="typcn typcn-device-desktop menu-icon"></i>
         <span class="menu-title">Dashboard</span>
       </a>
     </li>
     {{-- ROLE & PERMISSION --}}
     @php
     $rolePermissionActive =
     request()->routeIs('company.roles.*') ||
     request()->routeIs('company.permissions.*');
     @endphp

     <li class="nav-item {{ $rolePermissionActive ? 'active' : '' }}">
       <a class="nav-link"
         data-bs-toggle="collapse"
         href="#role-permission-menu"
         aria-expanded="{{ $rolePermissionActive ? 'true' : 'false' }}"
         aria-controls="role-permission-menu">
         <i class="typcn typcn-briefcase menu-icon"></i>
         <span class="menu-title">Role & Permission</span>
         <i class="typcn typcn-chevron-right menu-arrow"></i>
       </a>

       <div class="collapse {{ $rolePermissionActive ? 'show' : '' }}" id="role-permission-menu">
         <ul class="nav flex-column sub-menu">

           {{-- PERMISSIONS --}}
           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.permissions.*') ? 'active' : '' }}"
               href="{{ route('company.permissions.index', auth()->user()->company->slug) }}">
               Permission
             </a>
           </li>

           {{-- ROLES --}}
           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.roles.*') ? 'active' : '' }}"
               href="{{ route('company.roles.index', auth()->user()->company->slug) }}">
               Roles
             </a>
           </li>

         </ul>
       </div>
     </li>
     {{-- USER LIST --}}
     @php
     $userActive =
     request()->routeIs('company.users.*');
     @endphp

     <li class="nav-item {{ $userActive ? 'active' : '' }}">
       <a class="nav-link"
         data-bs-toggle="collapse"
         href="#user-list-menu"
         aria-expanded="{{ $userActive ? 'true' : 'false' }}"
         aria-controls="user-list-menu">
         <i class="typcn typcn-briefcase menu-icon"></i>
         <span class="menu-title">Users</span>
         <i class="typcn typcn-chevron-right menu-arrow"></i>
       </a>

       <div class="collapse {{ $userActive ? 'show' : '' }}" id="user-list-menu">
         <ul class="nav flex-column sub-menu">
           {{-- User --}}
           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.users.*') ? 'active' : '' }}"
               href="{{ route('company.users.index', auth()->user()->company->slug) }}">
               Users List
             </a>
           </li>
         </ul>
       </div>
     </li>

     {{-- ITEM LIST --}}
     @php
     $itemActive =
     request()->routeIs('company.items.*')||
     request()->routeIs('company.label_config.*');
     @endphp

     <li class="nav-item {{ $itemActive ? 'active' : '' }}">
       <a class="nav-link"
         data-bs-toggle="collapse"
         href="#item-list-menu"
         aria-expanded="{{ $itemActive ? 'true' : 'false' }}"
         aria-controls="item-list-menu">
         <i class="typcn typcn-briefcase menu-icon"></i>
         <span class="menu-title">Items</span>
         <i class="typcn typcn-chevron-right menu-arrow"></i>
       </a>

       <div class="collapse {{ $itemActive ? 'show' : '' }}" id="item-list-menu">
         <ul class="nav flex-column sub-menu">

           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.items.*') ? 'active' : '' }}"
               href="{{ route('company.items.index', auth()->user()->company->slug) }}">
               Items List
             </a>
           </li>
           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.label_config*') ? 'active' : '' }}"
               href="{{ route('company.label_config.index', auth()->user()->company->slug) }}">
               Label Config
             </a>
           </li>

           <li class="nav-item">
             <a class="nav-link
                   {{ request()->routeIs('company.label.print*') ? 'active' : '' }}"
               href="{{ route('company.label.print', auth()->user()->company->slug) }}">
               Label Printing
             </a>
           </li>
         </ul>
       </div>
     </li>
   </ul>
 </nav>