<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $company->name }} | Login</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

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

                        <div class="brand-logo text-center mb-3">
                            <h3 class="text-primary">{{ $company->name }}</h3>
                        </div>

                        <h4>Company Login</h4>
                        <h6 class="fw-light">Sign in to your ERP dashboard</h6>

                        <form method="POST" action="{{ route('company.login', $company->slug) }}">
                            @csrf

                            <div class="form-group mt-3">
                                <input type="email"
                                       name="email"
                                       value="{{ old('email') }}"
                                       class="form-control form-control-lg @error('email') is-invalid @enderror"
                                       placeholder="Email address"
                                       required>
                                @error('email')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-group mt-3">
                                <input type="password"
                                       name="password"
                                       class="form-control form-control-lg @error('password') is-invalid @enderror"
                                       placeholder="Password"
                                       required>
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            @if ($errors->any())
                                <div class="alert alert-danger mt-3 text-center">
                                    {{ $errors->first() }}
                                </div>
                            @endif

                            <div class="mt-4 d-grid gap-2">
                                <button type="submit"
                                        class="btn btn-primary btn-lg fw-medium auth-form-btn">
                                    LOGIN
                                </button>
                            </div>
                        </form>

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
