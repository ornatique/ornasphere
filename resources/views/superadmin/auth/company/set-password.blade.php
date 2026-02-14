<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Set Password</title>

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

                        {{-- Logo --}}
                        <div class="brand-logo text-center">
                            <img src="{{ asset('celestial/assets/images/logo.svg') }}" alt="logo">
                        </div>

                        <h4 class="text-center">Set Your Password 🔐</h4>
                        <h6 class="fw-light text-center mb-4">
                            Create a new password to activate your account
                        </h6>

                        {{-- FORM --}}
                        <form method="POST" action="{{ route('password.set.update', $token) }}">
                            @csrf


                            {{-- New Password --}}
                            <div class="form-group">
                                <label>New Password</label>
                                <input type="password"
                                       name="password"
                                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                                       placeholder="Enter new password"
                                       required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            {{-- Confirm Password --}}
                            <div class="form-group mt-3">
                                <label>Confirm Password</label>
                                <input type="password"
                                       name="password_confirmation"
                                       class="form-control form-control-lg"
                                       placeholder="Confirm password"
                                       required>
                            </div>

                            {{-- Submit --}}
                            <div class="mt-4 d-grid gap-2">
                                <button type="submit"
                                        class="btn btn-primary btn-lg fw-medium auth-form-btn">
                                    SET PASSWORD
                                </button>
                            </div>
                        </form>

                        {{-- Errors --}}
                        @if ($errors->any())
                            <div class="alert alert-danger mt-3 text-center">
                                {{ $errors->first() }}
                            </div>
                        @endif

                        {{-- Info --}}
                        <div class="text-center mt-4 text-muted small">
                            This is a one-time setup link. <br>
                            After setting your password, you will be redirected to login.
                        </div>

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
