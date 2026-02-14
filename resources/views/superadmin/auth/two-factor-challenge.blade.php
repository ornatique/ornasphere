<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification</title>

    {{-- CSS --}}
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/typicons.font/font/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
</head>

<body>
<div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
        <div class="content-wrapper d-flex align-items-center auth px-0">
            <div class="row w-100 mx-0">
                <div class="col-lg-4 mx-auto">

                    <div class="auth-form-transparent text-left py-5 px-4 px-sm-5">

                        <div class="brand-logo text-center">
                            <img src="{{ asset('celestial/assets/images/logo.svg') }}" alt="logo">
                        </div>

                        <h4>Two-Factor Verification 🔐</h4>
                        <h6 class="fw-light">Enter the OTP from Google Authenticator</h6>

                        <form method="POST" action="/two-factor-challenge">
                            @csrf

                            <div class="form-group mt-4">
                                <input type="text"
                                       name="code"
                                       class="form-control form-control-lg text-center"
                                       placeholder="Enter 6 digit OTP"
                                       maxlength="6"
                                       required>
                            </div>

                            <div class="mt-3 d-grid gap-2">
                                <button type="submit"
                                        class="btn btn-primary btn-lg fw-medium auth-form-btn">
                                    VERIFY OTP
                                </button>
                            </div>
                        </form>

                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 text-center">
                                {{ $errors->first() }}
                            </div>
                        @endif

                    </div>

                </div>
            </div>
        </div>
    </div>
</div>

{{-- JS --}}
<script src="{{ asset('celestial/assets/vendors/js/vendor.bundle.base.js') }}"></script>
<script src="{{ asset('celestial/assets/js/off-canvas.js') }}"></script>
<script src="{{ asset('celestial/assets/js/template.js') }}"></script>

</body>
</html>

