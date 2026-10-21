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
    $formatRoleName = fn($role) => ucwords(str_replace(['_', '-'], ' ', trim((string) $role)));
    $roleNames = method_exists($user, 'getRoleNames')
        ? $user->getRoleNames()->map($formatRoleName)->implode(', ')
        : '';
    $roleLabel = $roleNames ?: $formatRoleName($user->role ?? 'User');
    $planStatus = $companyPlanStatus ?? \App\Services\CompanyPlanService::status($company);
    $planExpired = (bool) data_get($planStatus, 'expired', false);
@endphp

<div class="content-wrapper profile-management">
    <header class="pm-heading"><div><span class="pm-eyebrow">Company settings</span><h1>Profile management</h1><p>Manage your company, branches and account settings in one place.</p></div></header>
    <nav class="pm-nav" aria-label="Profile sections">
        <a href="#profile-details">Profile details</a>
        @if($canManageBranches ?? false)<a href="#branch-management">Branches</a>@endif
        @if(!$company->parent_company_id && $user->hasRole('company_admin'))<a href="#opening-balances">Opening balances</a>@endif
        @if($user->hasRole('company_admin'))<a href="#voucher-settings">Voucher settings</a>@endif
    </nav>
    @include('company.profile.branches')
    @if(!$company->parent_company_id && $user->hasRole('company_admin'))
    @include('company.profile.opening_balances')
    @endif
    <div class="card profile-card" id="profile-details">
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
                <div class="profile-plan-banner {{ $planExpired ? 'is-expired' : 'is-active' }}">
                    <div>
                        <span>Plan Status</span>
                        <strong>{{ $planExpired ? 'Expired' : 'Active' }}</strong>
                    </div>
                    <div>
                        <span>Expire Date & Time</span>
                        <strong>{{ data_get($planStatus, 'expires_at_view') ?: '-' }}</strong>
                    </div>
                </div>
                <div class="profile-grid">
                    <div><span>Company Name</span><strong>{{ $company->name ?: '-' }}</strong></div>
                    <div><span>Company Email</span><strong>{{ $company->email ?: '-' }}</strong></div>
                    <div><span>Plan</span><strong>{{ $company->plan ? ucfirst($company->plan) : '-' }}</strong></div>
                    <div><span>Max Users</span><strong>{{ $company->max_users ?: '-' }}</strong></div>
                    <div><span>Plan Start Date & Time</span><strong>{{ data_get($planStatus, 'started_at_view') ?: '-' }}</strong></div>
                    <div><span>Plan Expire Date & Time</span><strong class="{{ $planExpired ? 'profile-status-inactive' : 'profile-status-active' }}">{{ data_get($planStatus, 'expires_at_view') ?: '-' }}</strong></div>
                    <div><span>Plan Status</span><strong class="{{ $planExpired ? 'profile-status-inactive' : 'profile-status-active' }}">{{ $planExpired ? 'Expired' : 'Active' }}</strong></div>
                    <div class="profile-grid-wide"><span>Company Address</span><strong>{{ trim(($company->address_1 ?? '') . ' ' . ($company->address_2 ?? '')) ?: '-' }}</strong></div>
                    <div><span>City</span><strong>{{ $company->city ?: '-' }}</strong></div>
                    <div><span>State</span><strong>{{ $company->state ?: '-' }}</strong></div>
                    <div><span>Country</span><strong>{{ $company->country ?: '-' }}</strong></div>
                </div>
            </div>

            @if($voucherConfiguration)
            <div class="profile-section voucher-settings" id="voucher-settings">
                <h2>Voucher Configuration</h2>
                <p class="voucher-settings-intro">Choose your company prefix and preview the next number for each voucher type.</p>
                @if(session('voucher_success'))<div class="alert alert-success">{{ session('voucher_success') }}</div>@endif
                @error('prefix')<div class="alert alert-danger">{{ $message }}</div>@enderror
                <form method="POST" action="{{ route('company.profile.voucher-series.update', $company->slug) }}">
                    @csrf @method('PUT')
                    <div class="voucher-settings-form mb-3">
                        <div>
                            <label for="voucher-prefix">Company voucher prefix</label>
                            <input id="voucher-prefix" name="prefix" class="form-control" maxlength="20" pattern="[A-Za-z0-9]+" value="{{ old('prefix', $voucherConfiguration['custom_prefix']) }}" placeholder="{{ $voucherConfiguration['default_prefix'] }}">
                            <small>Leave empty for company initials: {{ $voucherConfiguration['default_prefix'] }}. Letters and numbers only.</small>
                        </div>
                        <div><button class="btn btn-primary">Save Configuration</button></div>
                    </div>
                </form>
                <p>Each voucher type has a separate counter shared by the website and API. Changing the prefix continues the counter. Existing vouchers keep their numbers.</p>
                <div class="table-responsive voucher-settings-table"><table class="table table-bordered mb-0">
                    <thead><tr><th>Voucher type</th><th>Next voucher preview</th></tr></thead>
                    <tbody>@foreach($voucherConfiguration['series'] as $series)
                        <tr><td>{{ $series['label'] }}</td><td class="voucher-preview" data-module="{{ $series['module'] }}" data-number="{{ $series['next_number'] }}">{{ $series['preview'] }}</td></tr>
                    @endforeach</tbody>
                </table></div>
                <small>The final number is assigned when the voucher is saved. Counters do not reset yearly.</small>
            </div>
            @endif
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
<button type="button" id="profile-back-top" aria-label="Back to top" title="Back to top" hidden>
    <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m6 12 6-6 6 6M12 6v13"/></svg>
</button>
@endsection

@push('scripts')
<script>
const profileBackTop = document.getElementById('profile-back-top');
function updateProfileBackTop() {
    profileBackTop.hidden = window.scrollY < 300;
}
window.addEventListener('scroll', updateProfileBackTop, { passive: true });
updateProfileBackTop();
profileBackTop.addEventListener('click', function () {
    window.scrollTo({ top: 0, behavior: window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 'instant' : 'smooth' });
});
document.getElementById('voucher-prefix')?.addEventListener('input', function () {
    const prefix = this.value.trim().toUpperCase() || @json($voucherConfiguration['default_prefix'] ?? '');
    document.querySelectorAll('.voucher-preview').forEach(function (cell) {
        cell.textContent = prefix + '-' + cell.dataset.module + '-' + cell.dataset.number.padStart(3, '0');
    });
});
</script>
@endpush

@push('styles')
<style>
    #profile-back-top {position:fixed;right:24px;bottom:24px;width:48px;height:48px;border:1px solid #9aaaff;border-radius:50%;background:#5265cf;color:#fff;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 16px #0005;cursor:pointer;z-index:1000}
    #profile-back-top[hidden] {display:none}
    #profile-back-top:hover {background:#6478e1}
    #profile-back-top:focus-visible {outline:3px solid #c4ceff;outline-offset:3px}
    @media(max-width:576px){#profile-back-top{right:16px;bottom:16px}}
    .voucher-settings { padding:24px; border:1px solid #484b66; border-radius:14px; background:#27283b; }
    .voucher-settings h2 { font-size:26px; font-weight:700; margin:0 0 10px; color:#fff; }
    .voucher-settings-intro { color:#c2c9de; margin-bottom:24px; }
    .voucher-settings-form { display:grid; grid-template-columns:minmax(220px,520px) auto; align-items:start; gap:20px; justify-content:start; padding:20px; background:#303148; border-radius:10px; }
    .voucher-settings-form label { display:block; font-weight:600; margin-bottom:8px; }
    .voucher-settings-form small { display:block; margin-top:8px; color:#c2c9de; }
    .voucher-settings-form button { margin-top:28px; min-height:46px; white-space:nowrap; }
    .voucher-settings-table { border:1px solid #484b66; border-radius:10px; margin:18px 0 12px; }
    .voucher-settings-table th { background:#34364f; padding:15px 18px; }
    .voucher-settings-table td { padding:14px 18px; vertical-align:middle; }
    .voucher-settings-table .voucher-preview { font-family:monospace; font-size:16px; font-weight:700; color:#c1cdff; letter-spacing:.4px; }
    .voucher-settings-table tbody tr:nth-child(even) { background:rgba(255,255,255,.025); }
    @media(max-width:767px) { .voucher-settings { padding:16px; } .voucher-settings-form { grid-template-columns:1fr; } .voucher-settings-form button { margin-top:0; width:100%; } }
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

    .profile-plan-banner {
        display: grid;
        grid-template-columns: repeat(2, minmax(160px, 1fr));
        gap: 12px;
        margin-bottom: 12px;
        padding: 14px;
        border: 1px solid rgba(255, 255, 255, 0.08);
        background: rgba(255, 255, 255, 0.035);
    }

    .profile-plan-banner.is-active {
        border-color: rgba(0, 210, 106, 0.35);
        background: rgba(0, 210, 106, 0.08);
    }

    .profile-plan-banner.is-expired {
        border-color: rgba(255, 59, 78, 0.45);
        background: rgba(255, 59, 78, 0.10);
    }

    .profile-plan-banner span {
        display: block;
        color: #b8b8d4;
        font-size: 12px;
        margin-bottom: 5px;
    }

    .profile-plan-banner strong {
        display: block;
        color: #fff;
        font-size: 15px;
        line-height: 1.35;
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
        .profile-grid,
        .profile-plan-banner {
            grid-template-columns: repeat(2, minmax(160px, 1fr));
        }
    }

    @media (max-width: 575px) {
        .profile-summary,
        .profile-grid,
        .profile-plan-banner {
            grid-template-columns: 1fr;
        }

        .profile-grid-wide {
            grid-column: span 1;
        }
    }

    .profile-management .pm-heading {margin:0 0 22px}
    .profile-management .pm-eyebrow {text-transform:uppercase;letter-spacing:1.5px;font-size:11px;font-weight:700;color:#abbcff}
    .profile-management h1 {font-size:28px;font-weight:700;color:#fff;margin:8px 0}
    .profile-management .pm-heading p {color:#bbc4dc;line-height:1.6;margin:0}
    .profile-management .pm-nav {display:flex;flex-wrap:wrap;gap:10px;margin-bottom:26px}
    .profile-management .pm-nav a {padding:11px 17px;background:#30344e;color:#dce5ff;border:1px solid #505977;border-radius:8px;text-decoration:none;font-weight:600;font-size:13px}
    .profile-management .pm-nav a:hover {background:#414c74}
    .profile-management .card {border:1px solid #444862;border-radius:14px;background:#26283c;margin-bottom:26px;scroll-margin-top:85px}
    .profile-management .card-header {padding:22px 24px;background:#2d3046;border-bottom:1px solid #444862;border-radius:14px 14px 0 0;gap:16px}
    .profile-management .card-header h3,.profile-management .card-header .card-title {font-size:21px;font-weight:700;color:#fff}
    .profile-management .card-body {padding:24px}
    .profile-management .card-body>p {color:#c1c9dd;line-height:1.7;margin-bottom:20px}
    .profile-management .btn {min-height:42px;padding:10px 16px!important;border-radius:8px;line-height:1.35!important;font-size:13px;font-weight:600;color:#fff}
    .profile-management .btn-primary,.profile-management .btn-info {background:#4d61c5;border:1px solid #788ae6;color:#fff}
    .profile-management .btn-outline-light,.profile-management .btn-secondary {background:#363e59;border:1px solid #687391;color:#edf1ff}
    .profile-management .btn-outline-danger,.profile-management .btn-danger {background:#482c3a;border:1px solid #a85b72;color:#ffd1dd}
    .profile-management .btn:hover {filter:brightness(1.12)}
    .profile-management label {font-size:13px;color:#ccd5e9;font-weight:600;margin-bottom:8px}
    .profile-management .form-control {min-height:46px;border:1px solid #555e7d;border-radius:8px;background:#30334d;color:#fff;padding:11px 13px;line-height:1.5}
    .profile-management input:focus,.profile-management select:focus,.profile-management textarea:focus {outline:2px solid #98aaff;outline-offset:2px}
    .profile-management a:focus-visible,.profile-management button:focus-visible,.profile-management summary:focus-visible {outline:2px solid #98aaff;outline-offset:3px}
    .profile-management #branch-management .branch-panel {border-radius:12px;background:#292d43;padding:22px}
    .profile-management #branch-management .branch-overview {padding:22px;border-radius:10px}
    .profile-management #branch-management .branch-actions {gap:10px}
    .profile-management #branch-management .branch-edit summary {color:#c4d0ff}
    .profile-management .profile-summary>div,.profile-management .profile-grid>div {border:1px solid #414961;border-radius:9px;padding:16px;background:#24273a;min-width:0;overflow-wrap:anywhere}
    .profile-management .profile-section {margin-top:28px;scroll-margin-top:85px}
    .profile-management .profile-section h5 {font-size:18px;margin-bottom:16px}
    .profile-management .voucher-settings h2 {font-size:21px}
    @media(max-width:767px){.profile-management .card-header,.profile-management .card-body{padding:18px}.profile-management h1{font-size:24px}.profile-management .pm-nav a{flex:1 1 140px;text-align:center}.profile-management .profile-grid,.profile-management .profile-summary{grid-template-columns:1fr}.profile-management #branch-management .branch-actions .btn{flex:1 1 150px}.profile-management .voucher-settings-form{padding:14px}}
</style>
@endpush
