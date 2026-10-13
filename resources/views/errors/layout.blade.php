<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? 'Error' }} | {{ config('app.name', 'OrnaSphere') }}</title>
    <link rel="stylesheet" href="{{ asset('celestial/assets/vendors/css/vendor.bundle.base.css') }}">
    <link rel="stylesheet" href="{{ asset('celestial/assets/css/vertical-layout-dark/style.css') }}">
    <style>
        body {
            min-height: 100vh;
            margin: 0;
            background: #1f2142;
            color: #ffffff;
            font-family: "Roboto", sans-serif;
        }

        .error-wrap {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .error-card {
            width: 100%;
            max-width: 720px;
            background: #282a4a;
            border: 1px solid #33365f;
            border-radius: 10px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.35);
            padding: 30px;
            text-align: center;
        }

        .error-logo {
            width: 420px;
            max-width: 100%;
            height: auto;
            margin: 0 auto 15px;
            display: block;
            object-fit: contain;
        }

        @media (max-width: 575.98px) {
            .error-logo {
                width: 320px;
            }
        }

        .error-code {
            font-size: 56px;
            line-height: 1;
            font-weight: 700;
            color: #ffab40;
            margin-bottom: 8px;
        }

        .error-title {
            font-size: 26px;
            font-weight: 700;
            margin-bottom: 8px;
            color: #ffffff;
        }

        .error-message {
            color: #c7c8df;
            margin-bottom: 24px;
            font-size: 15px;
        }

        .error-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-error-primary {
            background: #ff2c7d;
            border: none;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }

        .btn-error-secondary {
            background: #2d8cff;
            border: none;
            color: #fff;
            padding: 10px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
@php
    $defaultLogo = url('/celestial/assets/images/ornasphere-auth-logo.png');
    $homeUrl = url('/');
    $homeLabel = 'Go Home';

    $companyUser = auth()->user();
    $superAdminUser = auth('superadmin')->user();
    $requestCompanySlug = request()->segment(1) === 'company' ? request()->segment(2) : null;
    $requestCompany = $requestCompanySlug
        ? \App\Models\Company::where('slug', $requestCompanySlug)->where('status', true)->first()
        : null;

    if ($superAdminUser && \Illuminate\Support\Facades\Route::has('superadmin.dashboard')) {
        $homeUrl = route('superadmin.dashboard');
    } elseif (\Illuminate\Support\Facades\Route::has('company.dashboard')) {
        if ($requestCompany) {
            $homeUrl = route('company.dashboard', ['slug' => $requestCompany->slug]);
        } elseif (
            $companyUser
            && optional($companyUser->company)->slug
            && (int) optional($companyUser->company)->status === 1
        ) {
            $homeUrl = route('company.dashboard', ['slug' => $companyUser->company->slug]);
        }
    }
@endphp

<div class="error-wrap">
    <div class="error-card">
        <img src="{{ $defaultLogo }}" alt="OrnaSphere" class="error-logo">
        <div class="error-code">{{ $code ?? '500' }}</div>
        <div class="error-title">{{ $title ?? 'Something went wrong' }}</div>
        <div class="error-message">{{ $message ?? 'An unexpected error occurred.' }}</div>
        <div class="error-actions">
            <a href="{{ $homeUrl }}" class="btn-error-primary">{{ $homeLabel }}</a>
            <a href="javascript:history.back()" class="btn-error-secondary">Go Back</a>
        </div>
    </div>
</div>
</body>
</html>
