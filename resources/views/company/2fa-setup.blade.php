<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Two Factor Authentication</title>

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
                    <div class="col-lg-5 mx-auto">

                        <div class="auth-form-transparent text-left py-5 px-4 px-sm-5">

                            <div class="brand-logo text-center">
                                <img src="{{ asset('celestial/assets/images/logo.svg') }}" alt="logo">
                            </div>

                            <h4 class="text-center mb-3">🔐 Two-Factor Authentication</h4>
                            <p class="text-center text-muted mb-4">
                                Company Security Verification
                            </p>

                            @php
                            $user = auth()->user();
                            @endphp

                            {{-- ================= FIRST TIME: SHOW QR ================= --}}
                            @if(!$user->two_factor_confirmed_at)

                            <p class="text-center mb-3">
                                Scan this QR code using <strong>Google Authenticator</strong>
                            </p>

                            <div class="d-flex justify-content-center my-4">
                                <div class="p-3 bg-white rounded shadow-sm">
                                    <div class="text-center mb-4">
                                        <img
                                            src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($qrCodeUrl) }}"
                                            alt="2FA QR Code">
                                    </div>
                                </div>
                            </div>

                            <form method="POST"
                                action="{{ route('company.2fa.verify', $user->company->slug) }}">
                                @csrf

                                <div class="form-group">
                                    <input type="text"
                                        name="otp"
                                        maxlength="6"
                                        class="form-control form-control-lg text-center"
                                        placeholder="Enter 6 digit OTP"
                                        required>
                                </div>

                                <button type="submit"
                                    class="btn btn-success btn-lg w-100">
                                    Verify & Enable 2FA
                                </button>
                            </form>

                            @endif


                            {{-- ================= ALREADY ENABLED: OTP ONLY ================= --}}
                            @if($user->two_factor_confirmed_at)

                            <form method="POST"
                                action="{{ route('company.2fa.verify', $user->company->slug) }}">
                                @csrf

                                <div class="form-group">
                                    <input type="text"
                                        name="otp"
                                        maxlength="6"
                                        class="form-control form-control-lg text-center"
                                        placeholder="Enter OTP"
                                        required>
                                </div>

                                <button type="submit"
                                    class="btn btn-primary btn-lg w-100">
                                    Verify OTP
                                </button>
                            </form>

                            @endif


                            {{-- Errors --}}
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