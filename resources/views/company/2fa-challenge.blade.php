<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification</title>

    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 mx-auto">

                        <div class="auth-form-transparent py-5 px-4">

                            <div class="brand-logo text-center mb-4">
                                <img src="{{ asset('celestial/assets/images/logo.svg') }}" alt="logo">
                            </div>

                            <h4 class="text-center">Two-Factor Verification 🔐</h4>
                            <p class="text-center text-muted">
                                Enter OTP from Google Authenticator
                            </p>

                            <form method="POST" action="{{ route('company.2fa.verify', $slug) }}">
                                @csrf

                                <div class="form-group mt-4">
                                    <input type="text"
                                        name="otp"
                                        class="form-control form-control-lg text-center"
                                        placeholder="Enter 6 digit OTP"
                                        maxlength="6"
                                        required>
                                </div>

                                <div class="mt-3 d-grid gap-2">
                                    <button type="submit"
                                        class="btn btn-primary btn-lg">
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
</body>

</html>