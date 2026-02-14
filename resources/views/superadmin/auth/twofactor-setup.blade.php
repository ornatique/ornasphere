<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Two Factor Authentication</title>

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

                            <h4 class="text-center mb-4">🔐 Two Factor Authentication</h4>

                            @php
                            $user = auth('superadmin')->user();
                            @endphp


                            {{-- ================= ENABLE 2FA ================= --}}
                            @if(!$user->two_factor_secret)

                            <div class="text-center">
                                <p class="mb-4">Secure your account with Google Authenticator</p>

                                <form method="POST" action="/user/two-factor-authentication">
                                    @csrf
                                    <button type="submit"
                                        class="btn btn-primary btn-lg w-100">
                                        Enable 2FA
                                    </button>
                                </form>
                            </div>

                            @endif



                            {{-- ================= SHOW QR ================= --}}
                            @if($user->two_factor_secret && !$user->two_factor_confirmed_at)

                            <div class="text-center">

                                <p class="mb-3">Scan this QR code with Google Authenticator</p>

                                <div class="text-center my-4">
                                    <div class="d-flex justify-content-center">
                                        <div class="p-3 bg-white rounded shadow-sm">
                                            {!! $user->twoFactorQrCodeSvg() !!}
                                        </div>
                                    </div>
                                </div>


                                <form method="POST" action="/user/confirmed-two-factor-authentication">
                                    @csrf

                                    <div class="form-group">
                                        <input type="text"
                                            name="code"
                                            maxlength="6"
                                            class="form-control form-control-lg text-center"
                                            placeholder="Enter 6 digit OTP"
                                            required>
                                    </div>

                                    <button type="submit"
                                        class="btn btn-success btn-lg w-100">
                                        Confirm OTP
                                    </button>
                                </form>
                            </div>

                            @endif



                            {{-- ================= AFTER CONFIRM ================= --}}
                            @if($user->two_factor_confirmed_at)
                            <script>
                                window.location.href = "{{ route('superadmin.dashboard') }}";
                            </script>
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