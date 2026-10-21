<a href="{{ route('superadmin.companies.branches.show', [$company, $company]) }}" style="color:#c4cfff;font-weight:700;text-decoration:none">{{ $company->name }}</a>
<div class="mt-2"><a href="{{ route('superadmin.companies.branches.show', [$company, $company]) }}" style="font-size:12px;color:#aebfff">Manage users &amp; branches &rarr;</a></div>
@if($company->relationLoaded('branches') && $company->branches->isNotEmpty())
    <details class="company-branches mt-2">
        <summary style="cursor:pointer;color:#8bbaff">View Branches ({{ $company->branches->count() }})</summary>
        <div style="min-width:220px;white-space:normal">
            @foreach($company->branches as $branch)
                <div class="p-2 mt-2" style="border:1px solid #484b68;border-radius:6px">
                    <strong>{{ $branch->name }}</strong>
                    <span class="badge {{ $parentActive && $branch->status ? 'badge-success' : 'badge-secondary' }}">{{ $parentActive && $branch->status ? 'Active' : 'Inactive' }}</span>
                    <div class="mt-1">{{ ucfirst($branch->branch_type ?: 'Branch') }} &middot; {{ $branch->users_count }} users</div>
                    <div class="mt-1">{{ $branch->email }}</div>
                    <small>Plan managed by {{ $company->name }}</small>
                    <div class="mt-2"><a class="btn btn-sm btn-primary" href="{{ route('superadmin.companies.branches.show', [$company, $branch]) }}">Details &amp; users</a></div>
                    @unless($parentActive)<div><small>Main company is inactive</small></div>@endunless
                </div>
            @endforeach
        </div>
    </details>
@endif
