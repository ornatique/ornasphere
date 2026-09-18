<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Company Two Factor Authentication</title>

    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/typicons.font/font/typicons.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/mdi/css/materialdesignicons.min.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
    <style>
        .company-auth-page {
            min-height: 100vh;
        }

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
            margin-bottom: 18px !important;
            text-align: center;
        }

        .two-factor-panel {
            text-align: center;
        }

        .two-factor-panel .form-group {
            margin-bottom: 1rem;
        }

        .two-factor-panel .form-control-lg {
            height: 50px;
            padding: 0 28px;
            font-size: 0.95rem;
            line-height: 50px;
        }

        .two-factor-panel .btn-lg {
            height: 50px;
            padding: 0 24px;
            font-size: 0.9rem;
            line-height: 50px;
        }

        .company-auth-name {
            color: #ff1a68;
            font-weight: 700;
            margin-bottom: 10px;
        }

        .company-auth-title {
            margin-bottom: 6px;
        }

        .company-auth-subtitle {
            margin-bottom: 22px;
        }

        .company-auth-qr-box {
            display: inline-flex;
            padding: 14px;
            background: #fff;
            border-radius: 8px;
        }

        .company-auth-qr-box img {
            width: 220px;
            height: 220px;
            display: block;
        }

        @media (max-width: 575.98px) {
            .two-factor-logo {
                width: 320px !important;
            }

            .company-auth-qr-box img {
                width: 190px;
                height: 190px;
            }
        }
    </style>
</head>

<body>
    <div class="container-scroller">
        <div class="container-fluid page-body-wrapper full-page-wrapper">
            <div class="content-wrapper d-flex align-items-center auth px-0 company-auth-page">

                <div class="row w-100 mx-0">
                    <div class="col-lg-4 col-md-7 mx-auto">

                        <div class="auth-form-transparent py-5 px-4 px-sm-5 two-factor-panel">
                            @php
                                $user = auth()->user();
                                $companyName = optional($company)->name ?? optional($user->company)->name ?? config('app.name');
                                $defaultCompanyLogo = asset('celestial/assets/images/logo.svg') . '?v=' . @filemtime(public_path('celestial/assets/images/logo.svg'));
                                $companyLogo = $defaultCompanyLogo;
                            @endphp

                            <div class="brand-logo text-center two-factor-logo-wrap">
                                <img src="{{ $companyLogo }}" alt="company-logo" class="two-factor-logo" onerror="this.onerror=null;this.src='{{ $defaultCompanyLogo }}';">
                            </div>
                            <h3 class="company-auth-name">{{ $companyName }}</h3>

                            <h4 class="company-auth-title">Two-Factor Authentication</h4>
                            <p class="text-muted company-auth-subtitle">
                                Company Security Verification
                            </p>

                            @if(!$user->two_factor_confirmed_at)

                            <p class="text-center mb-3">
                                Scan this QR code using <strong>Google Authenticator</strong>
                            </p>

                            <div class="d-flex justify-content-center my-4">
                                <div class="company-auth-qr-box shadow-sm">
                                    <img
                                        src="https://api.qrserver.com/v1/create-qr-code/?size=250x250&data={{ urlencode($qrCodeUrl) }}"
                                        alt="2FA QR Code">
                                </div>
                            </div>

                            <form method="POST"
                                action="{{ route('company.2fa.verify', $user->company->slug) }}">
                                @csrf

                                <div class="form-group">
                                    <input type="text"
                                        name="otp"
                                        maxlength="6"
                                        class="form-control form-control-lg text-center js-otp-code"
                                        placeholder="Enter 6 digit OTP"
                                        inputmode="numeric"
                                        pattern="[0-9]{6}"
                                        autocomplete="one-time-code"
                                        required>
                                </div>

                                <button type="submit"
                                    class="btn btn-success btn-lg w-100">
                                    Verify & Enable 2FA
                                </button>
                            </form>

                            @endif

                            @if($user->two_factor_confirmed_at)

                            <form method="POST"
                                action="{{ route('company.2fa.verify', $user->company->slug) }}">
                                @csrf

                                <div class="form-group">
                                    <input type="text"
                                        name="otp"
                                        maxlength="6"
                                        class="form-control form-control-lg text-center js-otp-code"
                                        placeholder="Enter 6 digit OTP"
                                        inputmode="numeric"
                                        pattern="[0-9]{6}"
                                        autocomplete="one-time-code"
                                        required>
                                </div>

                                <button type="submit"
                                    class="btn btn-primary btn-lg w-100">
                                    Verify OTP
                                </button>
                            </form>

                            @endif

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
