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
                 {{ auth('superadmin')->user()->name }}
                </p>
                <p class="sidebar-designation">
                  Welcome
                </p>
              </div>
            </div>
          </li>
          <li class="nav-item active">
            <a class="nav-link" href="{{ route('superadmin.dashboard') }}">
              <i class="typcn typcn-device-desktop menu-icon"></i>
              <span class="menu-title">Dashboard </span>
            </a>
          </li>
          <li class="nav-item">
            <a class="nav-link" data-bs-toggle="collapse" href="#ui-basic" aria-expanded="false" aria-controls="ui-basic">
              <i class="typcn typcn-briefcase menu-icon"></i>
              <span class="menu-title">Company</span>
              <i class="typcn typcn-chevron-right menu-arrow"></i>
            </a>
            <div class="collapse" id="ui-basic">
              <ul class="nav flex-column sub-menu">
                <li class="nav-item"> <a class="nav-link" href="{{route('superadmin.companies.index')}}">Company List</a></li>
                <li class="nav-item"> <a class="nav-link" href="./pages/ui-features/buttons.html">Buttons</a></li>
              </ul>
            </div>
          </li>
        </ul>
      </nav>