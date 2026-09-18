 <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
     @php
         $superAdminNotificationSummary = $superAdminNotificationSummary ?? ['total' => 0, 'latest' => collect()];
         $superAdminNotificationTotal = (int) ($superAdminNotificationSummary['total'] ?? 0);
         $superAdminNotificationItems = collect($superAdminNotificationSummary['latest'] ?? []);
     @endphp
     <style>
         .superadmin-notification-bell {
             position: relative;
             width: 36px;
             height: 36px;
             border-radius: 12px;
             color: #fff;
         }

         .superadmin-notification-badge {
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

         .superadmin-notification-dropdown {
             width: 430px;
             max-width: calc(100vw - 24px);
             padding: 0;
             overflow: hidden;
             background: #25283a;
             border: 1px solid #363a57;
             border-radius: 14px;
             box-shadow: 0 18px 45px rgba(0, 0, 0, .35);
         }

         .superadmin-notification-header {
             display: flex;
             align-items: center;
             justify-content: space-between;
             gap: 12px;
             padding: 14px 16px;
             border-bottom: 1px solid #363a57;
         }

         .superadmin-notification-heading {
             color: #fff;
             font-size: 14px;
             font-weight: 700;
         }

         .superadmin-notification-subtitle {
             display: block;
             margin-top: 2px;
             color: #aeb4c7;
             font-size: 12px;
         }

         .superadmin-notification-read-btn {
             border: 0;
             background: transparent;
             color: #ff2a6d;
             font-size: 12px;
             font-weight: 700;
             text-decoration: underline;
             white-space: nowrap;
         }

         .superadmin-notification-scroll {
             max-height: min(520px, calc(100vh - 170px));
             overflow-y: auto;
             overflow-x: hidden;
         }

         .superadmin-notification-item {
             display: flex;
             align-items: flex-start;
             gap: 12px;
             padding: 14px 16px;
             color: #d8dbea;
             white-space: normal;
             border-bottom: 1px solid rgba(255, 255, 255, .06);
             background: transparent;
         }

         .superadmin-notification-item:hover,
         .superadmin-notification-item:focus {
             color: #fff;
             background: #30344b;
         }

         .superadmin-notification-item.is-unread {
             background: rgba(255, 23, 68, .08);
         }

         .superadmin-notification-icon {
             display: inline-flex;
             align-items: center;
             justify-content: center;
             flex: 0 0 38px;
             width: 38px;
             height: 38px;
             margin-top: 2px;
             border-radius: 14px;
             background: linear-gradient(135deg, #ff1764, #2f80ed);
             color: #fff;
             font-size: 17px;
         }

         .superadmin-notification-copy {
             min-width: 0;
             display: flex;
             flex-direction: column;
             gap: 3px;
         }

         .superadmin-notification-title {
             color: #fff;
             font-size: 12px;
             font-weight: 700;
             text-transform: uppercase;
         }

         .superadmin-notification-message {
             color: #d8dbea;
             font-size: 13px;
             line-height: 1.35;
         }

         .superadmin-notification-time {
             color: #9ba2b6;
             font-size: 12px;
         }

         .superadmin-notification-empty {
             padding: 22px 16px;
             color: #aeb4c7;
             font-size: 13px;
             text-align: center;
         }

         .superadmin-notification-footer {
             padding: 12px 16px;
             text-align: center;
             border-top: 1px solid #363a57;
         }

         .superadmin-notification-view-all {
             color: #ffffff;
             font-size: 13px;
             font-weight: 700;
         }
     </style>
     <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
         <a class="navbar-brand brand-logo" href="{{ route('superadmin.dashboard') }}"><img src="{{ asset('celestial/assets/images/ornasphere-logo.svg') }}" alt="logo" /></a>
         <a class="navbar-brand brand-logo-mini" href="{{ route('superadmin.dashboard') }}"><img src="{{ asset('celestial/assets/images/logo-mini.svg') }}" alt="logo" onerror="this.style.display='none';" /></a>
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
             <li class="nav-item dropdown d-flex align-items-center me-2">
                 <a class="nav-link dropdown-toggle d-flex align-items-center justify-content-center superadmin-notification-bell"
                     id="superAdminNotificationDropdown"
                     href="#"
                     data-bs-toggle="dropdown"
                     aria-expanded="false">
                     <i class="typcn typcn-bell"></i>
                     @if($superAdminNotificationTotal > 0)
                         <span class="superadmin-notification-badge">{{ $superAdminNotificationTotal > 99 ? '99+' : $superAdminNotificationTotal }}</span>
                     @endif
                 </a>
                 <div class="dropdown-menu dropdown-menu-end navbar-dropdown superadmin-notification-dropdown" aria-labelledby="superAdminNotificationDropdown">
                     <div class="superadmin-notification-header">
                         <div>
                             <div class="superadmin-notification-heading">Notifications</div>
                             <span class="superadmin-notification-subtitle">
                                 {{ $superAdminNotificationTotal > 0 ? $superAdminNotificationTotal . ' unread activity' : 'No unread activity' }}
                             </span>
                         </div>

                         @if($superAdminNotificationTotal > 0)
                             <form method="POST" action="{{ route('superadmin.notifications.read') }}">
                                 @csrf
                                 <button type="submit" class="superadmin-notification-read-btn">Mark all read</button>
                             </form>
                         @endif
                     </div>

                     <div class="superadmin-notification-scroll">
                         @forelse($superAdminNotificationItems as $notification)
                             @php
                                 $notificationUrl = route('superadmin.notifications.open', $notification->id);
                             @endphp

                             <a class="dropdown-item superadmin-notification-item {{ is_null($notification->read_at) ? 'is-unread' : '' }}" href="{{ $notificationUrl }}">
                                 <div class="superadmin-notification-icon">
                                     <i class="typcn typcn-user-add-outline mx-0"></i>
                                 </div>
                                 <div class="superadmin-notification-copy">
                                     <span class="superadmin-notification-title">{{ $notification->title }}</span>
                                     <span class="superadmin-notification-message">{{ $notification->message }}</span>
                                     <span class="superadmin-notification-time">{{ optional($notification->created_at)->diffForHumans() }}</span>
                                 </div>
                             </a>
                         @empty
                             <div class="superadmin-notification-empty">No unread notifications.</div>
                         @endforelse
                     </div>

                     <div class="superadmin-notification-footer">
                         <a href="{{ route('superadmin.notifications.index') }}" class="superadmin-notification-view-all">View all notifications</a>
                     </div>
                 </div>
             </li>
             <li class="nav-item nav-profile dropdown">
                 <a class="nav-link dropdown-toggle  pl-0 pr-0" href="#" data-bs-toggle="dropdown" id="profileDropdown">
                     <i class="typcn typcn-user-outline me-0"></i>
                     <span class="nav-profile-name"> {{ auth('superadmin')->user()->name }}</span>
                 </a>
                 <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
                     <a class="dropdown-item">
                         <i class="typcn typcn-cog text-primary"></i>
                         Settings
                     </a>
                     <form method="POST" action="{{ route('superadmin.logout') }}">
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
