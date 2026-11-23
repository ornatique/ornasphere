@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Company Details</h4>
                <p class="mb-0 text-muted">Read-only company information and user accounts.</p>
            </div>
            <div class="d-flex gap-2">
                <a href="{{ route('superadmin.companies.index') }}" class="btn btn-secondary">Back to Companies</a>
                @if(!$archiveState || $archiveState->status === 'active')
                    <a href="{{ route('superadmin.companies.edit', $company->id) }}" class="btn btn-primary">Edit Company</a>
                @endif
            </div>
        </div>

        <div class="card-body">
            @if($archiveState && $archiveState->status !== 'active')
                <div class="alert alert-warning" role="status">
                    This company is paused for archive/restore. Details are read-only until that operation is finished.
                </div>
            @endif

            <div class="table-responsive">
                <table class="table table-bordered">
                    <tbody>
                        <tr><th>Company</th><td>{{ $company->name }}</td><th>Email</th><td>{{ $company->email ?: '-' }}</td></tr>
                        <tr><th>Access</th><td>{{ $company->status ? 'Active' : 'Inactive' }}</td><th>Archive state</th><td>{{ $archiveState->status ?? 'Active' }}</td></tr>
                        <tr><th>Plan</th><td>{{ $company->plan ? ucwords(str_replace('_', ' ', $company->plan)) : '-' }}</td><th>User limit</th><td>{{ $company->max_users }}</td></tr>
                        <tr><th>Plan starts</th><td>{{ optional($company->plan_started_at)->format('d-m-Y h:i A') ?: '-' }}</td><th>Plan expires</th><td>{{ optional($company->plan_expires_at)->format('d-m-Y h:i A') ?: '-' }}</td></tr>
                        <tr><th>Users</th><td>{{ $company->users_count }}</td><th>Branches</th><td>{{ $company->branches_count }}</td></tr>
                        <tr><th>City</th><td>{{ $company->city ?: '-' }}</td><th>Country</th><td>{{ $company->country ?: '-' }}</td></tr>
                    </tbody>
                </table>
            </div>

            <h5 class="mt-4">Company Users</h5>
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th></tr></thead>
                    <tbody>
                        @forelse($company->users as $user)
                            <tr>
                                <td>{{ $user->name }}</td>
                                <td>{{ $user->email }}</td>
                                <td>{{ $user->role ?: '-' }}</td>
                                <td>{{ $user->is_active ? 'Active' : 'Inactive' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="text-center">No users found.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
