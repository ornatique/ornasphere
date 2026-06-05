 <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
     @php
        $company = optional(auth()->user())->company;
        $companyName = optional($company)->name ?: config('app.name', 'Company');
        $companyShortName = \Illuminate\Support\Str::limit($companyName, 20, '...');
        $companyLogo = !empty(optional($company)->company_logo)
            ? asset('public/' . ltrim($company->company_logo, '/'))
            : asset('celestial/assets/images/logo.svg');
        $miniLogo = !empty(optional($company)->company_logo)
            ? asset('public/' . ltrim($company->company_logo, '/'))
            : asset('celestial/assets/images/logo-mini.svg');
     @endphp
     <style>
         .company-top-brand {
             gap: 8px;
             min-width: 0;
         }

         .company-top-brand-name {
             display: inline-block;
             max-width: 120px;
             overflow: hidden;
             color: #ffffff;
             font-size: 16px;
             font-weight: 700;
             line-height: 1;
             text-align: left;
             white-space: nowrap;
         }
     </style>
     <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
         <a class="navbar-brand brand-logo d-flex align-items-center justify-content-center company-top-brand" href="{{ route('company.dashboard',auth()->user()->company->slug) }}">
             <span class="company-top-brand-name" title="{{ $companyName }}">{{ $companyShortName }}</span>
         </a>
         <a class="navbar-brand brand-logo-mini" href="{{ route('company.dashboard',auth()->user()->company->slug) }}"><img src="{{ $miniLogo }}" alt="logo" style="height:34px; width:34px; object-fit:cover; border-radius:6px;" /></a>
         <button class="navbar-toggler navbar-toggler align-self-center d-none d-lg-flex" type="button" data-bs-toggle="minimize">
             <span class="typcn typcn-th-menu"></span>
         </button>
     </div>
     <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
         <ul class="navbar-nav navbar-nav-right">
             <!-- <li class="nav-item d-none d-lg-flex  me-2">
                 <a class="nav-link" href="#">
                     Help
                 </a>
             </li> -->
             <!-- <li class="nav-item dropdown d-flex">
                 <a class="nav-link count-indicator dropdown-toggle d-flex justify-content-center align-items-center" id="messageDropdown" href="#" data-bs-toggle="dropdown">
                     <i class="typcn typcn-message-typing"></i>
                     <span class="count bg-success">2</span>
                 </a>
                 <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list" aria-labelledby="messageDropdown">
                     <p class="mb-0 fw-normal float-start dropdown-header">Messages</p>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <img src="../assets/images/faces/face4.jpg" alt="image" class="profile-pic">
                         </div>
                         <div class="preview-item-content flex-grow">
                             <h6 class="preview-subject ellipsis fw-normal">David Grey
                             </h6>
                             <p class="fw-light small-text mb-0">
                                 The meeting is cancelled
                             </p>
                         </div>
                     </a>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <img src="../assets/images/faces/face2.jpg" alt="image" class="profile-pic">
                         </div>
                         <div class="preview-item-content flex-grow">
                             <h6 class="preview-subject ellipsis fw-normal">Tim Cook
                             </h6>
                             <p class="fw-light small-text mb-0">
                                 New product launch
                             </p>
                         </div>
                     </a>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <img src="../assets/images/faces/face3.jpg" alt="image" class="profile-pic">
                         </div>
                         <div class="preview-item-content flex-grow">
                             <h6 class="preview-subject ellipsis fw-normal"> Johnson
                             </h6>
                             <p class="fw-light small-text mb-0">
                                 Upcoming board meeting
                             </p>
                         </div>
                     </a>
                 </div>
             </li>
             <li class="nav-item dropdown  d-flex">
                 <a class="nav-link count-indicator dropdown-toggle d-flex align-items-center justify-content-center" id="notificationDropdown" href="#" data-bs-toggle="dropdown">
                     <i class="typcn typcn-bell me-0"></i>
                     <span class="count bg-danger">2</span>
                 </a>
                 <div class="dropdown-menu dropdown-menu-right navbar-dropdown preview-list" aria-labelledby="notificationDropdown">
                     <p class="mb-0 fw-normal float-start dropdown-header">Notifications</p>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <div class="preview-icon bg-success">
                                 <i class="typcn typcn-info-large mx-0"></i>
                             </div>
                         </div>
                         <div class="preview-item-content">
                             <h6 class="preview-subject fw-normal">Application Error</h6>
                             <p class="fw-light small-text mb-0">
                                 Just now
                             </p>
                         </div>
                     </a>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <div class="preview-icon bg-warning">
                                 <i class="typcn typcn-cog mx-0"></i>
                             </div>
                         </div>
                         <div class="preview-item-content">
                             <h6 class="preview-subject fw-normal">Settings</h6>
                             <p class="fw-light small-text mb-0">
                                 Private message
                             </p>
                         </div>
                     </a>
                     <a class="dropdown-item preview-item">
                         <div class="preview-thumbnail">
                             <div class="preview-icon bg-info">
                                 <i class="typcn typcn-user-outline mx-0"></i>
                             </div>
                         </div>
                         <div class="preview-item-content">
                             <h6 class="preview-subject fw-normal">New user registration</h6>
                             <p class="fw-light small-text mb-0">
                                 2 days ago
                             </p>
                         </div>
                     </a>
                 </div>
             </li> -->
             <li class="nav-item nav-profile dropdown">
                 <a class="nav-link dropdown-toggle  pl-0 pr-0" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                     <i class="typcn typcn-user-outline me-0"></i>
                     <span class="nav-profile-name"> {{ auth()->user()->name }}</span>
                 </a>
                 <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                     <a class="dropdown-item">
                         <i class="typcn typcn-cog text-primary"></i>
                         Settings
                     </a>
                     <form method="POST"   action="{{ route('company.logout', auth()->user()->company->slug) }}">
                         @csrf

                         <button type="submit" class="dropdown-item">
                             <i class="typcn typcn-power text-primary"></i>
                             Logout
                         </button>
                     </form>

                 </div>
             </li>
         </ul>
         <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-bs-toggle="offcanvas">
             <span class="typcn typcn-th-menu"></span>
         </button>
     </div>
 </nav>
