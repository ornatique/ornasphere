@if($canManageBranches ?? false)
<style>
#branch-management .branch-toolbar { display:flex; align-items:center; justify-content:space-between; gap:16px; flex-wrap:wrap; }
#branch-management .branch-muted { color:#bfc4d8; font-size:13px; }
#branch-management h5 { font-size:18px; font-weight:600; color:#fff; margin-bottom:6px; }
#branch-management .branch-panel { padding:22px; border:1px solid #494b68; border-radius:8px; background:#292b42; }
#branch-management .branch-status { padding:6px 12px; border-radius:20px; font-weight:600; font-size:12px; }
#branch-management .branch-status.active { color:#7ee4b6; background:#173f37; }
#branch-management .branch-status.inactive { color:#ffb0b7; background:#4a2735; }
#branch-management .branch-login { padding:12px 16px; background:#23253a; border-radius:6px; overflow-wrap:anywhere; }
#branch-management .branch-login span { display:block; color:#bfc4d8; font-size:12px; margin-bottom:5px; }
#branch-management .branch-login a { color:#94baff; }
#branch-management summary { cursor:pointer; font-weight:600; color:#fff; padding:12px 0; }
#branch-management .branch-create { border:1px solid #494b68; border-radius:8px; padding:0 20px; }
#branch-management .branch-create summary { padding:16px 0; }
#branch-management .branch-create summary span { float:right; }
#branch-management .branch-create form { padding:8px 0 20px; }
#branch-management .branch-edit { margin-top:10px; border-top:1px solid #494b68; }
#branch-management label { display:block; margin-bottom:6px; }
#branch-management .form-control { min-height:46px; color:#fff; }
#branch-management .branch-field-message { display:block; color:#ffb0b7; margin-top:5px; }
#branch-management .branch-overview { display:flex; justify-content:space-between; align-items:center; gap:20px; padding:18px; margin-bottom:20px; background:#202236; border:1px solid #494b68; border-radius:8px; }
#branch-management .branch-overview strong { display:block; color:#fff; font-size:18px; margin:4px 0; }
#branch-management .branch-capacity { text-align:right; flex-shrink:0; }
#branch-management .branch-grid { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:20px; align-items:start; }
#branch-management .branch-grid .branch-panel { margin-bottom:0!important; }
#branch-management .branch-number { display:block; color:#a9b8e2; font-size:12px; text-transform:uppercase; letter-spacing:1px; margin-bottom:8px; }
#branch-management .branch-facts { display:grid; grid-template-columns:1fr 1fr; gap:16px; margin:18px 0; }
#branch-management .branch-facts dt { font-weight:400; color:#bfc4d8; font-size:12px; margin-bottom:5px; }
#branch-management .branch-facts dd { color:#fff; margin:0; overflow-wrap:anywhere; }
#branch-management .branch-address { grid-column:1/-1; }
#branch-management .branch-actions { display:flex; gap:10px; flex-wrap:wrap; margin:16px 0; }
#branch-management .branch-actions .btn { margin:0; }
#branch-management .branch-login a { display:block; font-size:13px; line-height:1.6; }
#branch-management .branch-edit summary { padding:16px 0; }
#branch-management .branch-empty { grid-column:1/-1; padding:32px; text-align:center; border:1px dashed #626581; border-radius:8px; }
#branch-management .btn:disabled { background:#383b50; border-color:#555970; color:#c4c7d8; opacity:1; cursor:not-allowed; }
@media(max-width:1100px) { #branch-management .branch-grid { grid-template-columns:1fr; } }
@media(max-width:576px) { #branch-management .branch-overview { align-items:flex-start; flex-direction:column; } #branch-management .branch-capacity { text-align:left; } #branch-management .branch-panel { padding:16px; } }
</style>
<div class="card mb-4" id="branch-management">
    <div class="card-header branch-toolbar"><div><h4 class="card-title mb-1">Branch Management</h4><span class="branch-muted">{{ $branches->count() }} branches</span></div>
<a class="btn btn-info" href="{{ route('company.dashboard', ['slug'=>$company->slug, 'branch'=>'all']) }}">All Branches Dashboard</a></div>
    <div class="card-body">
        @php($branchLimitReached = $branches->count() >= \App\Services\CompanyBranches::LIMIT)
        @if($branchLimitReached || $errors->has('branch_limit'))
            <div role="alert" class="mb-3 p-3" style="background:#403827;border:1px solid #96763a;border-radius:6px;color:#ffe0a0">
                {{ \App\Services\CompanyBranches::LIMIT_MESSAGE }}
            </div>
        @endif
        <div class="branch-overview">
            <div><span class="branch-muted">Main company</span><strong>{{ $company->name }}</strong><span class="branch-muted">Each branch has its own users, stock and transactions. View combined data from All Branches Dashboard.</span></div>
            <div class="branch-capacity"><strong>{{ $branches->count() }} / {{ \App\Services\CompanyBranches::LIMIT }}</strong><span class="branch-muted">Branch slots used</span></div>
        </div>
        @if($branchLimitReached)
            <button type="button" class="btn btn-secondary mb-4" disabled>Create New Branch — Limit Reached (2/2)</button>
        @else
        <details class="branch-create mb-4" @if(old('_branch_form') === 'create' && $errors->any()) open @endif><summary>Create New Branch <span>+</span></summary>
        <form method="POST" action="{{ route('company.branches.store', $company->slug) }}">@csrf
            <input type="hidden" name="_branch_form" value="create">
            <div class="row">
            @foreach(['name'=>'Branch name','city'=>'City','address_1'=>'Address','admin_name'=>'Branch admin name','email'=>'Branch admin email'] as $field=>$label)
                <div class="col-md-6 mb-3"><label for="branch-{{ $field }}">{{ $label }}</label><input class="form-control" id="branch-{{ $field }}" name="{{ $field }}" type="{{ $field === 'email' ? 'email' : 'text' }}" value="{{ old($field) }}" @required(in_array($field,['name','admin_name','email']))></div>
            @endforeach
                <div class="col-md-6 mb-3"><label for="branch-type">Branch type</label><select id="branch-type" class="form-control" name="branch_type"><option value="manufacturing">Manufacturing</option><option value="retail" @selected(old('branch_type') === 'retail')>Retail</option></select></div>
                <div class="col-md-6 mb-3"><label for="branch-password">Admin password (at least 12 characters)</label><input id="branch-password" type="password" name="password" class="form-control" minlength="12" required autocomplete="new-password"></div>
                <div class="col-md-6 mb-3"><label for="branch-password-confirm">Confirm password</label><input id="branch-password-confirm" type="password" name="password_confirmation" class="form-control" minlength="12" required autocomplete="new-password"></div>
            </div><button class="btn btn-success">Create Branch</button>
        @if($errors->any())
            @foreach($errors->messages() as $field => $messages)
                <template class="branch-field-error" data-field="{{ $field }}">{{ $messages[0] }}</template>
            @endforeach
            @endif
        </form></details>
        @endif
        <div class="branch-grid">
        @forelse($branches as $branch)
        <div class="branch-panel mb-3">
            <span class="branch-number">Branch {{ $loop->iteration }}</span>
            <div class="branch-toolbar"><h5>{{ $branch->name }}</h5><span class="branch-status {{ $branch->status ? 'active' : 'inactive' }}">{{ $branch->status ? 'Active' : 'Inactive' }}</span></div>
            <dl class="branch-facts">
                <div><dt>Branch type</dt><dd>{{ ucfirst($branch->branch_type ?? 'Not specified') }}</dd></div>
                <div><dt>City</dt><dd>{{ $branch->city ?: 'Not provided' }}</dd></div>
                <div class="branch-address"><dt>Address</dt><dd>{{ $branch->address_1 ?: 'Not provided' }}</dd></div>
            </dl>
            <div class="branch-login"><span>Login link for branch staff</span><a href="{{ route('company.login', $branch->slug) }}">{{ route('company.login', $branch->slug) }}</a></div>
            <div class="branch-actions">
                <a class="btn btn-sm btn-info" href="{{ route('company.dashboard', ['slug'=>$company->slug,'branch'=>$branch->slug]) }}">View Dashboard</a>
                <button type="button" class="btn btn-sm btn-outline-light branch-copy" data-url="{{ route('company.login', $branch->slug) }}">Copy Login Link</button>
                <form method="POST" action="{{ route('company.branches.status', [$company->slug, $branch->id]) }}" class="m-0">
                    @csrf @method('PATCH')
                    <input type="hidden" name="status" value="{{ $branch->status ? 0 : 1 }}">
                    <button type="submit" class="btn btn-sm {{ $branch->status ? 'btn-outline-danger' : 'btn-success' }}" aria-label="{{ $branch->status ? 'Deactivate' : 'Activate' }} {{ $branch->name }}">{{ $branch->status ? 'Deactivate Branch' : 'Activate Branch' }}</button>
                </form>
            </div>
            <span class="branch-muted branch-copy-message" role="status"></span>
            <details class="branch-edit" @if(old('_branch_form') == (string)$branch->id && $errors->any()) open @endif><summary>Edit Branch Details</summary>
                <form method="POST" action="{{ route('company.branches.update', [$company->slug,$branch->id]) }}" class="mt-3">@csrf @method('PUT')
                    <input type="hidden" name="_branch_form" value="{{ $branch->id }}">
                    <div class="row">
                    @foreach(['name'=>'Name','city'=>'City','address_1'=>'Address'] as $field=>$label)<div class="col-md-4 mb-3"><label>{{ $label }}</label><input name="{{ $field }}" class="form-control" value="{{ old('_branch_form') == (string)$branch->id ? old($field, $branch->$field) : $branch->$field }}" @required($field === 'name')></div>@endforeach
                    <div class="col-md-4 mb-3"><label>Type</label><select name="branch_type" class="form-control"><option value="manufacturing" @selected($branch->branch_type === 'manufacturing')>Manufacturing</option><option value="retail" @selected($branch->branch_type === 'retail')>Retail</option></select></div>
                    <div class="col-md-4 mb-3"><label>Status</label><select name="status" class="form-control"><option value="1" @selected($branch->status)>Active</option><option value="0" @selected(!$branch->status)>Inactive</option></select></div>
                    </div><button class="btn btn-primary">Save Branch</button>
                @if($errors->any())
            @foreach($errors->messages() as $field => $messages)
                <template class="branch-field-error" data-field="{{ $field }}">{{ $messages[0] }}</template>
            @endforeach
            @endif
        </form>
            </details>
        </div>
        @empty<div class="branch-empty"><h5>No branches yet</h5><p class="branch-muted mb-0">Choose Create New Branch above to add your first retail or manufacturing branch.</p></div>@endforelse
        </div>

    </div>
</div>
@endif

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('#branch-management .branch-copy').forEach(function (button) {
        button.addEventListener('click', async function () {
            const message = button.closest('.branch-panel').querySelector('.branch-copy-message');
            try {
                await navigator.clipboard.writeText(button.dataset.url);
                message.textContent = 'Login link copied.';
            } catch (error) {
                message.textContent = 'Please select and copy the login link above.';
            }
        });
    });
    const submitted = @json(old('_branch_form'));
    document.querySelectorAll('#branch-management form').forEach(function (form) {
        const formMarker = form.querySelector('[name="_branch_form"]');
        if (!submitted || !formMarker || formMarker.value !== String(submitted)) return;
        form.querySelectorAll('.branch-field-error').forEach(function (template) {
            const field = form.elements.namedItem(template.dataset.field);
            if (!field) return;
            field.setAttribute('aria-invalid', 'true');
            const message = document.createElement('small');
            message.className = 'branch-field-message';
            message.textContent = template.content.textContent;
            field.after(message);
        });
    });
});
</script>
@endpush
