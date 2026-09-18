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
    <style>
        .two-factor-logo {
            width: 420px !important;
            max-width: 100% !important;
            height: auto !important;
            max-height: none !important;
            object-fit: contain;
            display: block;
            margin-left: auto;
            margin-right: auto;
        }

        .two-factor-logo-wrap {
            margin-bottom: -100px !important;
            text-align: center;
        }

        .two-factor-panel {
            text-align: center;
        }

        @media (max-width: 575.98px) {
            .two-factor-logo {
                width: 320px !important;
            }
        }
    </style>
</head>

<body>
<div class="container-scroller">
    <div class="container-fluid page-body-wrapper full-page-wrapper">
        <div class="content-wrapper d-flex align-items-center auth px-0">
            <div class="row w-100 mx-0">
                <div class="col-lg-4 mx-auto">

                    <div class="auth-form-transparent py-5 px-4 px-sm-5 two-factor-panel">

                        <div class="brand-logo two-factor-logo-wrap">
                            <img src="{{ asset('celestial/assets/images/logo.svg') }}?v={{ @filemtime(public_path('celestial/assets/images/logo.svg')) }}" alt="logo" class="two-factor-logo">
                        </div>

                        <h4>Two-Factor Verification</h4>
                        <h6 class="fw-light">Enter the OTP from Google Authenticator</h6>

                        <form method="POST" action="/superadmin/two-factor-challenge">
                            @csrf

                            <div class="form-group mt-4">
                                <input type="text"
                                       name="code"
                                       class="form-control form-control-lg text-center js-otp-code"
                                       placeholder="Enter 6 digit OTP"
                                       inputmode="numeric"
                                       pattern="[0-9]{6}"
                                       autocomplete="one-time-code"
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
<script>
    document.querySelectorAll('.js-otp-code').forEach(function(input) {
        input.addEventListener('input', function() {
            this.value = this.value.replace(/\D/g, '').slice(0, 6);
        });
    });
</script>

</body>
</html>
