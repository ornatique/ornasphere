<!DOCTYPE html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  @php
    $companyName = optional(optional(auth()->user())->company)->name ?: config('app.name', 'OrnaSphere');
  @endphp
  <title>{{ $companyName }} | Admin Panel</title>
  <!-- base:css -->
  <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
  <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
  <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/typicons.font/font/typicons.css') }}">
  <!-- endinject -->
  <!-- plugin css for this page -->
  <!-- End plugin css for this page -->
  <!-- inject:css -->
  <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
  <!-- endinject -->
  <!-- <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css"> -->
  <link rel="shortcut icon" href="{{ asset('celestial/assets/images/favicon.png') }}" />
<link href="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/css/select2.min.css" rel="stylesheet" />
<link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet" />
<style>
  html,
  body,
  .container-scroller,
  .page-body-wrapper,
  .main-panel {
    background: #1e1e2f;
  }

  .container-scroller {
    min-height: 100vh;
  }

  .content-wrapper form > .alert.alert-danger {
    display: none !important;
  }

  .content-wrapper select.form-control:not(.form-control-sm):not([multiple]):not([size]),
  .content-wrapper select.form-select:not(.form-select-sm):not([multiple]):not([size]) {
    width: 100%;
    min-height: 44px;
    height: 44px;
    padding: 10px 12px;
    line-height: 1.4;
    color: #cfd3e6;
    background-color: #2f2e55;
    border-color: rgba(255, 255, 255, 0.08);
  }

  .content-wrapper select.form-control:not(.form-control-sm):not([multiple]):not([size]):focus,
  .content-wrapper select.form-select:not(.form-select-sm):not([multiple]):not([size]):focus {
    border-color: rgba(125, 145, 255, 0.75);
    box-shadow: none;
  }

  .content-wrapper .select2-container {
    width: 100% !important;
  }

  .content-wrapper .select2-container--default .select2-selection--single,
  .content-wrapper .select2-container--bootstrap4 .select2-selection--single {
    min-height: 44px;
    height: 44px;
    background-color: #2f2e55;
    border-color: rgba(255, 255, 255, 0.08);
  }

  .content-wrapper .select2-container--default .select2-selection--single .select2-selection__rendered,
  .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__rendered {
    min-height: 42px;
    line-height: 42px;
    padding-left: 12px;
    color: #cfd3e6;
  }

  .content-wrapper .select2-container--default .select2-selection--single .select2-selection__arrow,
  .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow {
    height: 100%;
    top: 0;
    right: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .content-wrapper .select2-container--default .select2-selection--single .select2-selection__arrow b,
  .content-wrapper .select2-container--bootstrap4 .select2-selection--single .select2-selection__arrow b {
    position: static;
    margin: 0;
    transform: none;
  }

  .content-wrapper .dataTables_scrollBody,
  .content-wrapper .table-responsive,
  .content-wrapper .sales-list-grid-wrap,
  .content-wrapper .sale-grid-wrap,
  .content-wrapper .tree-cutting-list-scroll,
  .content-wrapper .tree-cutting-receive-list-scroll,
  .content-wrapper .tree-cutting-scroll,
  .content-wrapper .tree-cutting-receive-scroll,
  .content-wrapper .casting-heating-scroll,
  .content-wrapper .casting-metal-scroll,
  .content-wrapper .casting-release-scroll,
  .content-wrapper .casting-sorting-scroll,
  .content-wrapper .vacuum-voucher-detail-scroll,
  .content-wrapper .voucher-history-timeline,
  .content-wrapper .history-table-wrap {
    scrollbar-width: thin;
    scrollbar-color: transparent transparent;
  }

  .content-wrapper .dataTables_scrollBody:hover,
  .content-wrapper .table-responsive:hover,
  .content-wrapper .sales-list-grid-wrap:hover,
  .content-wrapper .sale-grid-wrap:hover,
  .content-wrapper .tree-cutting-list-scroll:hover,
  .content-wrapper .tree-cutting-receive-list-scroll:hover,
  .content-wrapper .tree-cutting-scroll:hover,
  .content-wrapper .tree-cutting-receive-scroll:hover,
  .content-wrapper .casting-heating-scroll:hover,
  .content-wrapper .casting-metal-scroll:hover,
  .content-wrapper .casting-release-scroll:hover,
  .content-wrapper .casting-sorting-scroll:hover,
  .content-wrapper .vacuum-voucher-detail-scroll:hover,
  .content-wrapper .voucher-history-timeline:hover,
  .content-wrapper .history-table-wrap:hover {
    scrollbar-color: rgba(125, 145, 255, 0.7) rgba(255, 255, 255, 0.08);
  }

  .content-wrapper .dataTables_scrollBody::-webkit-scrollbar,
  .content-wrapper .table-responsive::-webkit-scrollbar,
  .content-wrapper .sales-list-grid-wrap::-webkit-scrollbar,
  .content-wrapper .sale-grid-wrap::-webkit-scrollbar,
  .content-wrapper .tree-cutting-list-scroll::-webkit-scrollbar,
  .content-wrapper .tree-cutting-receive-list-scroll::-webkit-scrollbar,
  .content-wrapper .tree-cutting-scroll::-webkit-scrollbar,
  .content-wrapper .tree-cutting-receive-scroll::-webkit-scrollbar,
  .content-wrapper .casting-heating-scroll::-webkit-scrollbar,
  .content-wrapper .casting-metal-scroll::-webkit-scrollbar,
  .content-wrapper .casting-release-scroll::-webkit-scrollbar,
  .content-wrapper .casting-sorting-scroll::-webkit-scrollbar,
  .content-wrapper .vacuum-voucher-detail-scroll::-webkit-scrollbar,
  .content-wrapper .voucher-history-timeline::-webkit-scrollbar,
  .content-wrapper .history-table-wrap::-webkit-scrollbar {
    width: 10px;
    height: 10px;
  }

  .content-wrapper .dataTables_scrollBody::-webkit-scrollbar-track,
  .content-wrapper .table-responsive::-webkit-scrollbar-track,
  .content-wrapper .sales-list-grid-wrap::-webkit-scrollbar-track,
  .content-wrapper .sale-grid-wrap::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-list-scroll::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-receive-list-scroll::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-scroll::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-receive-scroll::-webkit-scrollbar-track,
  .content-wrapper .casting-heating-scroll::-webkit-scrollbar-track,
  .content-wrapper .casting-metal-scroll::-webkit-scrollbar-track,
  .content-wrapper .casting-release-scroll::-webkit-scrollbar-track,
  .content-wrapper .casting-sorting-scroll::-webkit-scrollbar-track,
  .content-wrapper .vacuum-voucher-detail-scroll::-webkit-scrollbar-track,
  .content-wrapper .voucher-history-timeline::-webkit-scrollbar-track,
  .content-wrapper .history-table-wrap::-webkit-scrollbar-track {
    background: transparent;
  }

  .content-wrapper .dataTables_scrollBody::-webkit-scrollbar-thumb,
  .content-wrapper .table-responsive::-webkit-scrollbar-thumb,
  .content-wrapper .sales-list-grid-wrap::-webkit-scrollbar-thumb,
  .content-wrapper .sale-grid-wrap::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-list-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-receive-list-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-receive-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .casting-heating-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .casting-metal-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .casting-release-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .casting-sorting-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .vacuum-voucher-detail-scroll::-webkit-scrollbar-thumb,
  .content-wrapper .voucher-history-timeline::-webkit-scrollbar-thumb,
  .content-wrapper .history-table-wrap::-webkit-scrollbar-thumb {
    background: transparent;
    border-radius: 10px;
  }

  .content-wrapper .dataTables_scrollBody:hover::-webkit-scrollbar-track,
  .content-wrapper .table-responsive:hover::-webkit-scrollbar-track,
  .content-wrapper .sales-list-grid-wrap:hover::-webkit-scrollbar-track,
  .content-wrapper .sale-grid-wrap:hover::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-list-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-receive-list-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .tree-cutting-receive-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .casting-heating-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .casting-metal-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .casting-release-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .casting-sorting-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .vacuum-voucher-detail-scroll:hover::-webkit-scrollbar-track,
  .content-wrapper .voucher-history-timeline:hover::-webkit-scrollbar-track,
  .content-wrapper .history-table-wrap:hover::-webkit-scrollbar-track {
    background: rgba(255, 255, 255, 0.08);
  }

  .content-wrapper .dataTables_scrollBody:hover::-webkit-scrollbar-thumb,
  .content-wrapper .table-responsive:hover::-webkit-scrollbar-thumb,
  .content-wrapper .sales-list-grid-wrap:hover::-webkit-scrollbar-thumb,
  .content-wrapper .sale-grid-wrap:hover::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-list-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-receive-list-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .tree-cutting-receive-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .casting-heating-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .casting-metal-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .casting-release-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .casting-sorting-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .vacuum-voucher-detail-scroll:hover::-webkit-scrollbar-thumb,
  .content-wrapper .voucher-history-timeline:hover::-webkit-scrollbar-thumb,
  .content-wrapper .history-table-wrap:hover::-webkit-scrollbar-thumb {
    background: rgba(125, 145, 255, 0.7);
  }
</style>

  @stack('styles')


</head>

<body>

  <div class="container-scroller">
    <!-- <div class="row p-0 m-0 proBanner" id="proBanner">
      <div class="col-md-12 p-0 m-0">
        <div class="card-body card-body-padding px-3 d-flex align-items-center justify-content-between">
          <div class="ps-lg-3">
            <div class="d-flex align-items-center justify-content-between">
              <p class="mb-0 fw-medium me-3 buy-now-text">Free 24/7 customer support, updates, and more with this template!</p>
              <a href="https://www.bootstrapdash.com/product/celestial-admin-template/" target="_blank" class="btn me-2 buy-now-btn border-0">Buy Now</a>
            </div>
          </div>
          <div class="d-flex align-items-center justify-content-between">
            <a href="https://www.bootstrapdash.com/product/celestial-admin-template/"><i class="typcn typcn-home me-3 text-white"></i></a>
            <button id="bannerClose" class="btn border-0 p-0">
              <i class="typcn typcn-delete text-white"></i>
            </button>
          </div>
        </div>
      </div>
    </div> -->
    <!-- partial:partials/_navbar.html -->
    @include('company_layout.header')
    <!-- partial -->
    <div class="container-fluid page-body-wrapper">
      <!-- partial:partials/_settings-panel.html -->
      <div class="theme-setting-wrapper">
        <div id="theme-settings" class="settings-panel">
          <i class="settings-close typcn typcn-delete-outline"></i>
          <p class="settings-heading">SIDEBAR SKINS</p>
          <div class="sidebar-bg-options" id="sidebar-light-theme">
            <div class="img-ss rounded-circle bg-light border me-3"></div>
            Light
          </div>
          <div class="sidebar-bg-options selected" id="sidebar-dark-theme">
            <div class="img-ss rounded-circle bg-dark border me-3"></div>
            Dark
          </div>
          <p class="settings-heading mt-2">HEADER SKINS</p>
          <div class="color-tiles mx-0 px-4">
            <div class="tiles success"></div>
            <div class="tiles warning"></div>
            <div class="tiles danger"></div>
            <div class="tiles primary"></div>
            <div class="tiles info"></div>
            <div class="tiles dark"></div>
            <div class="tiles default border"></div>
          </div>
        </div>
      </div>
      <!-- partial -->
      <!-- partial:partials/_sidebar.html -->
      @include('company_layout.sidebar')
      <!-- partial -->
      <div class="main-panel">
        @yield('content')
        <!-- content-wrapper ends -->
        <!-- partial:partials/_footer.html -->
        @include('company_layout.footer')
        <!-- partial -->
      </div>
      <!-- main-panel ends -->
    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->


  </script>
  <!-- base:js -->
  <script src="{{ asset('celestial/assets/vendors/js/vendor.bundle.base.js') }}"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>

  <!-- endinject -->
  <!-- Plugin js for this page-->
  <!-- End plugin js for this page-->
  <!-- inject:js -->
  <script src="{{ asset('celestial/assets/js/off-canvas.js') }}"></script>
  <script src="{{ asset('celestial/assets/js/hoverable-collapse.js') }}"></script>
  <script src="{{ asset('celestial/assets/js/template.js') }}?v={{ @filemtime(public_path('celestial/assets/js/template.js')) }}"></script>
  <script src="{{ asset('celestial/assets/js/settings.js') }}"></script>
  <script src="{{ asset('celestial/assets/js/todolist.js') }}"></script>
  <!-- endinject -->
  <!-- plugin js for this page -->
  <script src="{{ asset('celestial/assets/vendors/progressbar.js/progressbar.min.js') }}"></script>
  <script src="{{ asset('celestial/assets/vendors/chart.js/chart.umd.js') }}"></script>
  <script src="{{ asset('celestial/assets/js/jquery.cookie.js') }}"></script>
  <!-- End plugin js for this page -->
  <!-- Custom js for this page-->
  <script src="{{ asset('celestial/assets/js/dashboard.js') }}"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <!-- <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.js"></script> -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.0.13/dist/js/select2.min.js"></script>
<script>
document.addEventListener('blur', function (event) {
    const input = event.target;

    if (!(input instanceof HTMLInputElement) || input.dataset.skipDecimalFormat === '1') {
        return;
    }

    const step = String(input.getAttribute('step') || '').trim();
    const explicitPlaces = input.dataset.decimalPlaces ? parseInt(input.dataset.decimalPlaces, 10) : null;
    let decimalPlaces = Number.isInteger(explicitPlaces) ? explicitPlaces : null;

    if (decimalPlaces === null) {
        if (step === '0.001' || step === '.001') {
            decimalPlaces = 3;
        } else if (step === '0.01' || step === '.01') {
            decimalPlaces = 2;
        }
    }

    if (decimalPlaces === null || input.value.trim() === '') {
        return;
    }

    const number = parseFloat(input.value);
    if (!Number.isFinite(number)) {
        return;
    }

    const zeroThreshold = Math.pow(10, -(decimalPlaces + 1)) / 2;
    input.value = (Math.abs(number) < zeroThreshold ? 0 : number).toFixed(decimalPlaces);
}, true);
</script>

  <!-- End custom js for this page-->
  @stack('scripts')
  @if(session('success') || session('error') || session('warning') || session('info'))
<script>
document.addEventListener("DOMContentLoaded", function () {

    const Toast = Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer
            toast.onmouseleave = Swal.resumeTimer
        }
    });

    @if(session('success'))
        Toast.fire({
            icon: 'success',
            title: "{{ session('success') }}"
        });
    @endif

    @if(session('error'))
        Toast.fire({
            icon: 'error',
            title: "{{ session('error') }}"
        });
    @endif

    @if(session('warning'))
        Toast.fire({
            icon: 'warning',
            title: "{{ session('warning') }}"
        });
    @endif

    @if(session('info'))
        Toast.fire({
            icon: 'info',
            title: "{{ session('info') }}"
        });
    @endif

});
</script>
@endif
</body>

</html>
