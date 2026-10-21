@extends('layout.admin')
@section('content')
<style>
.branch-page{width:100%;max-width:none;margin:0;color:#edf0fa}.branch-page .bp-panel{background:#26273b;border:1px solid #41435d;border-radius:14px;padding:26px;margin-bottom:24px}.branch-page .bp-header,.branch-page .bp-access,.branch-page .bp-user-head,.branch-page .bp-footer{display:flex;align-items:center;justify-content:space-between;gap:18px;flex-wrap:wrap}.branch-page h2{font-size:28px;font-weight:700;margin:6px 0 8px;color:#fff}.branch-page h3{font-size:20px;color:#fff;margin:0}.branch-page p{color:#bec4d9;line-height:1.6;margin:6px 0 0}.branch-page .bp-eyebrow{font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#aab8ff;font-weight:700}.branch-page .bp-button{display:inline-flex;align-items:center;justify-content:center;min-height:44px;padding:10px 20px;border-radius:8px;border:1px solid #646d9d;background:#363e63;color:#fff;font-weight:600;cursor:pointer;text-decoration:none}.branch-page .bp-button:hover{background:#46517e}.branch-page .bp-save{background:#5265cf;border-color:#7182eb}.branch-page .bp-danger{background:#492c39;border-color:#a5566e;color:#ffceda}.branch-page .bp-facts{display:grid;grid-template-columns:2fr 1fr 1fr;gap:16px;margin:24px 0}.branch-page .bp-fact{background:#202133;border:1px solid #393b53;border-radius:9px;padding:16px;min-width:0}.branch-page .bp-fact span{display:block;color:#aeb6cf;font-size:12px;margin-bottom:7px}.branch-page .bp-fact strong{overflow-wrap:anywhere}.branch-page .bp-access{border-top:1px solid #41435d;padding-top:20px}.branch-page .bp-access p{max-width:850px;font-size:13px}.branch-page .bp-badge{display:inline-block;padding:6px 10px;border:1px solid #525976;border-radius:20px;font-size:12px;font-weight:600;background:#33394f;color:#dce3ff}.branch-page .bp-good{background:#193f38;color:#a7efd3;border-color:#367562}.branch-page .bp-muted{background:#433a2b;color:#f5d79c;border-color:#786646}.branch-page .bp-section{margin:30px 0 18px}.branch-page .bp-user{background:#26273b;border:1px solid #41435d;border-radius:12px;padding:22px;margin-bottom:16px}.branch-page .bp-user-head{padding-bottom:18px;border-bottom:1px solid #3c3e56}.branch-page .bp-user-head strong{font-size:17px;overflow-wrap:anywhere}.branch-page .bp-tags{display:flex;gap:8px;flex-wrap:wrap}.branch-page .bp-fields{display:grid;grid-template-columns:1fr 1.4fr 1fr;gap:20px;margin:20px 0}.branch-page label{display:block;color:#cbd2e7;font-size:13px;font-weight:600;margin-bottom:9px}.branch-page .bp-input{display:block;width:100%;height:46px!important;padding:10px 13px!important;border:1px solid #565a7a;border-radius:8px;background:#303149;color:#fff;font-size:14px;line-height:1.4;box-sizing:border-box}.branch-page .bp-input:focus{outline:2px solid #96a6ff;outline-offset:2px}.branch-page .bp-footer p{font-size:12px;margin:0}.branch-page .bp-notice{border-left:3px solid #edbd66;padding:12px 16px;background:#3a342a;color:#ffe0a4;margin-top:18px;border-radius:5px}@media(max-width:760px){.branch-page .bp-facts,.branch-page .bp-fields{grid-template-columns:1fr}.branch-page .bp-panel,.branch-page .bp-user{padding:18px}.branch-page h2{font-size:23px}.branch-page .bp-footer .bp-button{width:100%}}
</style>
<div class="content-wrapper">
<div class="branch-page">
    @if(session('success'))<div class="alert alert-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="alert alert-danger" role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif
    <section class="bp-panel">
        <div style="margin-bottom:24px;max-width:600px">
            <label for="company-switch">Select main company or branch</label>
            <select id="company-switch" class="bp-input" onchange="window.location.assign(this.value)">
                @foreach($locations as $location)
                    <option value="{{ route('superadmin.companies.branches.show', [$company, $location]) }}" @selected($location->id === $branch->id)>{{ $location->name }} — {{ $location->id === $company->id ? 'Main company' : 'Sub-branch' }}</option>
                @endforeach
            </select>
        </div>
        <div class="bp-header">
            <div><span class="bp-eyebrow">Company &amp; branch management</span><h2>{{ $branch->name }}</h2><p>Main company: {{ $company->name }}</p></div>
            <a class="bp-button" href="{{ route('superadmin.companies.index') }}">Back to companies</a>
        </div>
        <div class="bp-facts">
            <div class="bp-fact"><span>Company email</span><strong>{{ $branch->email ?: 'Not provided' }}</strong></div>
            <div class="bp-fact"><span>Company type</span><strong>{{ $branch->id === $company->id ? 'Main company' : ucfirst($branch->branch_type ?: 'Branch') }}</strong></div>
            <div class="bp-fact"><span>Total users</span><strong>{{ $users->total() }}</strong></div>
        </div>
        <div class="bp-access">
            <div><span class="bp-badge {{ $branch->status ? 'bp-good' : 'bp-muted' }}">Branch {{ $branch->status ? 'active' : 'inactive' }}</span><p>Deactivation signs out users and blocks access. For the main company, this also blocks its branches. Activation enables all related users, including individually inactive users. Activating the main company also activates its branches.</p></div>
            <form method="POST" action="{{ route('superadmin.companies.branches.status', [$company, $branch]) }}" onsubmit="return confirm('Change branch access for all its users?')">
                @csrf @method('PUT')
                <input type="hidden" name="status" value="{{ $branch->status ? 0 : 1 }}">
                <button class="bp-button {{ $branch->status ? 'bp-danger' : 'bp-save' }}">{{ $branch->status ? 'Deactivate company' : 'Activate company' }}</button>
            </form>
        </div>
        @unless($company->status)<div class="bp-notice">Access is blocked because the main company is inactive.</div>@endunless
    </section>
    <div class="bp-section"><h3>Company users</h3><p>Manage users belonging to {{ $branch->name }}.</p></div>
    @forelse($users as $user)
        <form class="bp-user" method="POST" action="{{ route('superadmin.companies.branches.users.update', [$company, $branch, $user]) }}">
            @csrf @method('PUT')
            <div class="bp-user-head">
                <strong>{{ $user->name }}</strong>
                <div class="bp-tags">
                    <span class="bp-badge">{{ str_replace('_', ' ', $user->roles->pluck('name')->implode(', ') ?: $user->role) }}</span>
                    <span class="bp-badge {{ $user->is_active ? 'bp-good' : 'bp-muted' }}">{{ $user->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="bp-badge {{ $user->two_factor_enabled ? 'bp-good' : 'bp-muted' }}">{{ $user->two_factor_enabled ? '2FA enabled' : '2FA setup required' }}</span>
                </div>
            </div>
            <div class="bp-fields">
                <div><label for="name-{{ $user->id }}">Full name</label><input class="bp-input" id="name-{{ $user->id }}" name="name" value="{{ $user->name }}" required maxlength="255"></div>
                <div><label for="email-{{ $user->id }}">Login email</label><input class="bp-input" id="email-{{ $user->id }}" name="email" type="email" value="{{ $user->email }}" required maxlength="255"></div>
                <div><label for="status-{{ $user->id }}">User access</label><select class="bp-input" id="status-{{ $user->id }}" name="is_active"><option value="1" @selected($user->is_active)>Active</option><option value="0" @selected(!$user->is_active)>Inactive (reset 2FA)</option></select></div>
            </div>
            <div class="bp-footer"><p>Email changes sign this user out. Deactivation also resets their 2FA.</p><button class="bp-button bp-save">Save changes</button></div>
        </form>
    @empty
        <div class="bp-panel"><h3>No users yet</h3><p>This branch has no user accounts.</p></div>
    @endforelse
    <div class="mt-3">{{ $users->links() }}</div>
</div>
</div>
@endsection
