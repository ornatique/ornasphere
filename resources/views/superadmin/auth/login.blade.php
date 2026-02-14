<!DOCTYPE html>
<html lang="en">

<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Super Admin Login</title>
    <!-- base:css -->
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/typicons.font/font/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
    <!-- endinject -->
    <!-- plugin css for this page -->
    <!-- End plugin css for this page -->
    <!-- inject:css -->
    <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
    <!-- endinject -->
    <link rel="stylesheet" href="{{ asset('celestial/assets/images/favicon.png') }}">
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">
                        <div class="auth-form-transparent text-left py-5 px-4 px-sm-5">
                            <div class="brand-logo">
                                <img src="{{ asset('celestial/assets/images/logo.svg') }}" alt="logo">
                            </div>
                            <h4>Hello! let's get started</h4>
                            <h6 class="fw-light">Sign in to continue.</h6>
                            <form method="POST" action="{{ route('superadmin.login.store') }}">
                                @csrf
                                <div class="form-group">
                                    <input type="email" name="email" class="form-control form-control-lg" id="exampleInputEmail1" placeholder="Email">
                                </div>
                                <div class="form-group">
                                    <input type="password" name="password" class="form-control form-control-lg" id="exampleInputPassword1" placeholder="Password">
                                </div>
                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit" class="btn btn-primary btn-lg fw-medium auth-form-btn" >SIGN IN</button>
                                </div>
                                <div class="my-2 d-flex justify-content-between align-items-center">
                                    <div class="form-check">
                                        <label class="form-check-label text-muted">
                                            <input type="checkbox" class="form-check-input">
                                            Keep me signed in
                                        </label>
                                    </div>
                                    <a href="#" class="auth-link text-light">Forgot password?</a>
                                </div>
                                <div class="mb-2 d-grid gap-2">
                                    <button type="button" class="btn btn-facebook auth-form-btn">
                                        <i class="typcn typcn-social-facebook-circular me-2"></i>Connect using facebook
                                    </button>
                                </div>
                                <div class="text-center mt-4 fw-light">
                                    Don't have an account? <a href="register.html" class="text-primary">Create</a>
                                </div>
                            </form>
                            @if ($errors->any())
                            <p style="color:red">{{ $errors->first() }}</p>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
            <!-- content-wrapper ends -->
        </div>
        <!-- page-body-wrapper ends -->
    </div>
    <!-- container-scroller -->
    <!-- base:js -->
    <script src="{{ asset('celestial/assets/vendors/js/vendor.bundle.base.js') }}"></script>

    <!-- endinject -->
    <!-- inject:js -->
    <script src="{{ asset('celestial/assets/js/off-canvas.js') }}"></script>
    <script src="{{ asset('celestial/assets/js/hoverable-collapse.js') }}"></script>
    <script src="{{ asset('celestial/assets/js/template.js') }}"></script>
    <script src="{{ asset('celestial/assets/js/settings.js') }}"></script>
    <script src="{{ asset('celestial/assets/js/todolist.js') }}"></script>
    <!-- endinject -->
</body>

</html>