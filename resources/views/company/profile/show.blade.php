@extends('company_layout.admin')

@section('content')
@php
    $profileImage = null;
    $rawProfilePath = trim((string) ($user->profile_image ?? ''));

    if ($rawProfilePath !== '') {
        $normalizedProfilePath = ltrim($rawProfilePath, '/');
        if (str_starts_with($normalizedProfilePath, 'public/')) {
            $normalizedProfilePath = substr($normalizedProfilePath, 7);
        }

        if (file_exists(public_path($normalizedProfilePath))) {
            $profileImage = asset('public/' . $normalizedProfilePath);
        } elseif (file_exists(storage_path('app/public/' . $normalizedProfilePath))) {
            $profileImage = asset('storage/' . $normalizedProfilePath);
        }
    }

    $initials = collect(explode(' ', trim((string) $user->name)))
        ->filter()
        ->take(2)
        ->map(fn($part) => strtoupper(substr($part, 0, 1)))
        ->implode('') ?: 'U';

    $statusText = (int) ($user->is_active ?? 1) === 1 ? 'Active' : 'Inactive';
    $statusClass = (int) ($user->is_active ?? 1) === 1 ? 'profile-status-active' : 'profile-status-inactive';
    $roleNames = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->implode(', ') : '';
    $roleLabel = $roleNames ?: ucwords(str_replace('_', ' ', (string) ($user->role ?? 'User')));
@endphp

<div class="content-wrapper">
    <div class="card profile-card">
        <div class="card-header profile-header">
            <div class="profile-title-wrap">
                <div class="profile-avatar">
                    <span>{{ $initials }}</span>
                    @if($profileImage)
                    <img src="{{ $profileImage }}" alt="profile image" onerror="this.style.display='none';">
                    @endif
                </div>
                <div>
                    <h4 class="card-title mb-1">Profile Details</h4>
                    <div class="profile-subtitle">{{ $user->name }}</div>
                </div>
            </div>
            <a href="{{ route('company.dashboard', $company->slug) }}" class="btn btn-primary">Back</a>
        </div>

        <div class="card-body">
            <div class="profile-summary">
                <div><span>Name</span><strong>{{ $user->name ?: '-' }}</strong></div>
                <div><span>Email</span><strong>{{ $user->email ?: '-' }}</strong></div>
                <div><span>Role</span><strong>{{ $roleLabel ?: '-' }}</strong></div>
                <div><span>Status</span><strong class="{{ $statusClass }}">{{ $statusText }}</strong></div>
            </div>

            <div class="profile-section">
                <h5>Contact</h5>
                <div class="profile-grid">
                    <div><span>Mobile No</span><strong>{{ $user->mobile_no ?: '-' }}</strong></div>
                    <div><span>Phone No</span><strong>{{ $user->phone_no ?: '-' }}</strong></div>
                    <div><span>Person Code</span><strong>{{ $user->person_code ?: '-' }}</strong></div>
                    <div><span>City</span><strong>{{ $user->city ?: '-' }}</strong></div>
                    <div class="profile-grid-wide"><span>Address</span><strong>{{ $user->address ?: '-' }}</strong></div>
                    <div><span>Area</span><strong>{{ $user->area ?: '-' }}</strong></div>
                    <div><span>Landmark</span><strong>{{ $user->landmark ?: '-' }}</strong></div>
                    <div><span>Pincode</span><strong>{{ $user->pincode ?: '-' }}</strong></div>
                </div>
            </div>

            <div class="profile-section">
                <h5>Company</h5>
                <div class="profile-grid">
                    <div><span>Company Name</span><strong>{{ $company->name ?: '-' }}</strong></div>
                    <div><span>Company Email</span><strong>{{ $company->email ?: '-' }}</strong></div>
                    <div><span>Plan</span><strong>{{ $company->plan ?: '-' }}</strong></div>
                    <div><span>Max Users</span><strong>{{ $company->max_users ?: '-' }}</strong></div>
                    <div class="profile-grid-wide"><span>Company Address</span><strong>{{ trim(($company->address_1 ?? '') . ' ' . ($company->address_2 ?? '')) ?: '-' }}</strong></div>
                    <div><span>City</span><strong>{{ $company->city ?: '-' }}</strong></div>
                    <div><span>State</span><strong>{{ $company->state ?: '-' }}</strong></div>
                    <div><span>Country</span><strong>{{ $company->country ?: '-' }}</strong></div>
                </div>
            </div>

            <div class="profile-section">
                <h5>Other Details</h5>
                <div class="profile-grid">
                    <div><span>GST No</span><strong>{{ $user->gst_no ?: '-' }}</strong></div>
                    <div><span>PAN No</span><strong>{{ $user->pan_no ?: '-' }}</strong></div>
                    <div><span>Aadhaar No</span><strong>{{ $user->aadhaar_no ?: '-' }}</strong></div>
                    <div><span>Hallmark License</span><strong>{{ $user->hallmark_license_no ?: '-' }}</strong></div>
                    <div><span>Birth Date</span><strong>{{ $user->birth_date ? \Carbon\Carbon::parse($user->birth_date)->format('d-m-Y') : '-' }}</strong></div>
                    <div><span>Anniversary Date</span><strong>{{ $user->anniversary_date ? \Carbon\Carbon::parse($user->anniversary_date)->format('d-m-Y') : '-' }}</strong></div>
                    <div><span>Joined At</span><strong>{{ optional($user->created_at)->format('d-m-Y / h:i A') ?: '-' }}</strong></div>
                    <div><span>Last Updated</span><strong>{{ optional($user->updated_at)->format('d-m-Y / h:i A') ?: '-' }}</strong></div>
                    <div class="profile-grid-wide"><span>Remarks</span><strong>{{ $user->remarks ?: '-' }}</strong></div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .profile-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }

    .profile-title-wrap {
        display: flex;
        align-items: center;
        gap: 14px;
    }

    .profile-avatar {
        position: relative;
        width: 54px;
        height: 54px;
        border-radius: 50%;
        overflow: hidden;
        border: 2px solid rgba(255, 255, 255, 0.18);
        background: linear-gradient(135deg, #287cff, #ff1f73);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .profile-avatar span {
        color: #fff;
        font-size: 18px;
        font-weight: 700;
        line-height: 1;
    }

    .profile-avatar img {
        position: absolute;
        inset: 0;
        width: 100%;
        height: 100%;
        object-fit: cover;
        background: #2d2e46;
    }

    .profile-subtitle {
        color: #b8b8d4;
        font-size: 13px;
    }

    .profile-summary,
    .profile-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(160px, 1fr));
        gap: 12px;
    }

    .profile-section {
        margin-top: 22px;
    }

    .profile-section h5 {
        color: #fff;
        font-size: 16px;
        margin-bottom: 12px;
    }

    .profile-summary > div,
    .profile-grid > div {
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.035);
        padding: 11px 12px;
        min-height: 64px;
    }

    .profile-summary span,
    .profile-grid span {
        display: block;
        color: #b8b8d4;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .profile-summary strong,
    .profile-grid strong {
        display: block;
        color: #fff;
        font-size: 14px;
        line-height: 1.35;
        overflow-wrap: anywhere;
    }

    .profile-status-active {
        color: #00d26a !important;
    }

    .profile-status-inactive {
        color: #ff3b4e !important;
    }

    .profile-grid-wide {
        grid-column: span 2;
    }

    @media (max-width: 1199px) {
        .profile-summary,
        .profile-grid {
            grid-template-columns: repeat(2, minmax(160px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .profile-summary,
        .profile-grid {
            grid-template-columns: 1fr;
        }

        .profile-grid-wide {
            grid-column: span 1;
        }
    }
</style>
@endpush
