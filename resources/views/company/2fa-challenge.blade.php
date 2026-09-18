<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>OTP Verification</title>

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
            <div class="content-wrapper d-flex align-items-center auth px-0 company-auth-page">
                <div class="row w-100 mx-0">
                    <div class="col-lg-4 col-md-7 mx-auto">

                        <div class="auth-form-transparent py-5 px-4 px-sm-5 two-factor-panel">
                            @php
                                $viewUser = $user ?? auth()->user();
                                $userCompany = optional($viewUser)->company;
                                $companyName = optional($company)->name ?? optional($userCompany)->name ?? config('app.name');
                                $defaultCompanyLogo = asset('celestial/assets/images/logo.svg') . '?v=' . @filemtime(public_path('celestial/assets/images/logo.svg'));
                                $companyLogo = $defaultCompanyLogo;
                            @endphp

                            <div class="brand-logo text-center two-factor-logo-wrap">
                                <img src="{{ $companyLogo }}" alt="company-logo" class="two-factor-logo" onerror="this.onerror=null;this.src='{{ $defaultCompanyLogo }}';">
                            </div>

                            <h3 class="company-auth-name">{{ $companyName }}</h3>
                            <h4 class="company-auth-title">Two-Factor Verification</h4>
                            <p class="text-muted company-auth-subtitle">
                                Enter OTP from Google Authenticator
                            </p>

                            <form method="POST" action="{{ route('company.2fa.verify', $slug) }}">
                                @csrf

                                <div class="form-group mt-4">
                                    <input type="text"
                                        name="otp"
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
    <script>
        document.querySelectorAll('.js-otp-code').forEach(function(input) {
            input.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 6);
            });
        });
    </script>
</body>

</html>
