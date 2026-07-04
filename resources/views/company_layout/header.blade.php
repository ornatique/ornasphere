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
     $notificationSummary = $companyNotificationSummary ?? ['total' => 0, 'latest' => collect()];
     $notificationTotal = (int) ($notificationSummary['total'] ?? 0);
     $notificationItems = collect($notificationSummary['latest'] ?? []);
     $isCompanyAdmin = auth()->user() && auth()->user()->hasRole('company_admin');
     $canNotifications = auth()->user() && ($isCompanyAdmin || auth()->user()->can('notification-view'));
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

         .company-notification-bell {
             position: relative;
             width: 36px;
             height: 36px;
             border-radius: 12px;
             color: #fff;
         }

         .company-notification-badge {
             position: absolute;
             top: 2px;
             right: 0;
             min-width: 18px;
             height: 18px;
             padding: 0 5px;
             border-radius: 999px;
             background: #ff1744;
             color: #fff;
             font-size: 11px;
             font-weight: 700;
             line-height: 18px;
             text-align: center;
         }

         .company-notification-dropdown {
             width: 430px;
             max-width: calc(100vw - 24px);
             padding: 0;
             overflow: hidden;
             background: #25283a;
             border: 1px solid #363a57;
             border-radius: 14px;
             box-shadow: 0 18px 45px rgba(0, 0, 0, .35);
         }

         .company-notification-header {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 12px;
             padding: 14px 16px;
             border-bottom: 1px solid #363a57;
         }

         .company-notification-heading {
             color: #fff;
             font-size: 14px;
             font-weight: 700;
         }

         .company-notification-subtitle {
             display: block;
             margin-top: 2px;
             color: #aeb4c7;
             font-size: 12px;
         }

         .company-notification-read-btn {
             border: 0;
             background: transparent;
             color: #ff2a6d;
             font-size: 12px;
             font-weight: 700;
             text-decoration: underline;
             white-space: nowrap;
         }

         .notification-scroll {
             max-height: min(520px, calc(100vh - 170px));
             overflow-y: auto;
             overflow-x: hidden;
         }

         .company-notification-item {
             display: flex;
             align-items: flex-start;
             gap: 12px;
             padding: 14px 16px;
             color: #d8dbea;
             white-space: normal;
             border-bottom: 1px solid rgba(255, 255, 255, .06);
             background: transparent;
         }

         .company-notification-item:hover,
         .company-notification-item:focus {
             color: #fff;
             background: #30344b;
         }

         .company-notification-item.is-unread {
             background: rgba(255, 23, 68, .08);
         }

         .company-notification-icon {
             display: inline-flex;
             align-items: center;
             justify-content: center;
             flex: 0 0 38px;
             width: 38px;
             height: 38px;
             margin-top: 2px;
             border-radius: 14px;
             background: linear-gradient(135deg, #ff1764, #7c4dff);
             color: #fff;
             font-size: 17px;
         }

         .company-notification-copy {
             min-width: 0;
             display: flex;
             flex-direction: column;
             gap: 3px;
         }

         .company-notification-title {
             color: #fff;
             font-size: 12px;
             font-weight: 700;
             text-transform: uppercase;
             letter-spacing: .02em;
         }

         .company-notification-message {
             color: #d8dbea;
             font-size: 13px;
             line-height: 1.35;
         }

         .company-notification-time {
             color: #9ba2b6;
             font-size: 12px;
         }

         .company-notification-empty {
             padding: 22px 16px;
             color: #aeb4c7;
             font-size: 13px;
             text-align: center;
         }

         .notification-scroll::-webkit-scrollbar {
             width: 7px;
         }

         .notification-scroll::-webkit-scrollbar-track {
             background: #202234;
         }

         .notification-scroll::-webkit-scrollbar-thumb {
             background: #585f83;
             border-radius: 999px;
         }

         .notification-scroll::-webkit-scrollbar-thumb:hover {
             background: #7b84b6;
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
             @if($canNotifications)
             <li class="nav-item dropdown d-flex align-items-center me-2">
                 <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-center company-notification-bell"
                     id="companyNotificationDropdown"
                     href="#"
                     data-bs-toggle="dropdown"
                     aria-expanded="false">
                     <i class="typcn typcn-bell"></i>
                     @if($notificationTotal > 0)
                     <span class="company-notification-badge">{{ $notificationTotal > 99 ? '99+' : $notificationTotal }}</span>
                     @endif
                 </a>
                 <div class="dropdown-menu dropdown-menu-end navbar-dropdown company-notification-dropdown"
                     aria-labelledby="companyNotificationDropdown">

                     <div class="company-notification-header">
                         <div>
                             <div class="company-notification-heading">Notifications</div>
                             <span class="company-notification-subtitle">
                                {{ $notificationTotal > 0 ? $notificationTotal . ' unread activity' : 'No unread activity' }}
                             </span>
                         </div>

                         @if($notificationTotal > 0)
                         <form method="POST" action="{{ route('company.notifications.read', auth()->user()->company->slug) }}">
                             @csrf
                             <button type="submit" class="company-notification-read-btn">
                                 Mark all read
                             </button>
                         </form>
                         @endif
                     </div>

                     <div class="notification-scroll">

                         @forelse($notificationItems as $notification)

                         @php
                         $notificationUrl = '#';

                         if (!empty($notification->route_name) && Route::has($notification->route_name)) {
                         try {
                         $notificationUrl = route(
                         $notification->route_name,
                         $notification->route_params ?: ['slug' => auth()->user()->company->slug]
                         );
                         } catch (\Throwable $e) {
                         $notificationUrl = '#';
                         }
                         }

                         if ($notificationUrl !== '#') {
                         $notificationUrl .= (\Illuminate\Support\Str::contains($notificationUrl, '?') ? '&' : '?')
                         . 'mark_notifications_read=1';
                         }

                         $isUnreadNotification = is_null($notification->read_at);
                         @endphp

                         <a class="dropdown-item company-notification-item {{ $isUnreadNotification ? 'is-unread' : '' }}"
                             href="{{ $notificationUrl }}">

                             <div class="company-notification-icon">
                                 <i class="typcn typcn-info-large mx-0"></i>
                             </div>

                             <div class="company-notification-copy">
                                 <span class="company-notification-title">
                                     {{ $notification->title }}
                                 </span>

                                 <span class="company-notification-message">
                                     {{ $notification->message }}
                                 </span>

                                 <span class="company-notification-time">
                                     {{ optional($notification->created_at)->diffForHumans() }}
                                 </span>
                             </div>

                         </a>

                         @empty

                         <div class="company-notification-empty">
                            No unread notifications.
                         </div>

                         @endforelse

                     </div>

                 </div>
             </li>
             @endif
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
                     <form method="POST" action="{{ route('company.logout', auth()->user()->company->slug) }}">
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
