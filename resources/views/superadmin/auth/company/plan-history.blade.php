@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h4 class="card-title mb-1">Company Plan History</h4>
                <p class="mb-0 text-muted">{{ $company->name }}</p>
            </div>
            <a href="{{ route('superadmin.companies.index') }}" class="btn btn-primary">Back</a>
        </div>

        <div class="card-body">
            <div class="plan-history-summary">
                <div class="summary-box">
                    <span>Company</span>
                    <strong>{{ $company->name }}</strong>
                </div>
                <div class="summary-box">
                    <span>Plan</span>
                    <strong>{{ $company->plan ? ucfirst($company->plan) : '-' }}</strong>
                </div>
                <div class="summary-box">
                    <span>Users</span>
                    <strong>{{ $company->users_count }} / {{ $company->max_users }}</strong>
                </div>
                <div class="summary-box">
                    <span>Status</span>
                    <strong class="{{ $planStatus['expired'] ? 'text-danger' : 'text-success' }}">
                        {{ $planStatus['expired'] ? 'Expired' : 'Active' }}
                    </strong>
                </div>
                <div class="summary-box">
                    <span>Start Date & Time</span>
                    <strong>{{ $planStatus['started_at_view'] ?: '-' }}</strong>
                </div>
                <div class="summary-box">
                    <span>Expire Date & Time</span>
                    <strong>{{ $planStatus['expires_at_view'] ?: '-' }}</strong>
                </div>
                <div class="summary-box">
                    <span>Total Renewals</span>
                    <strong>{{ $renewals->total() }}</strong>
                </div>
            </div>

            <div class="table-responsive mt-4">
                <table class="table plan-history-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Renewed At</th>
                            <th>Old Expire</th>
                            <th>New Expire</th>
                            <th>Renewed By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($renewals as $renewal)
                            <tr>
                                <td>{{ $loop->iteration + ($renewals->currentPage() - 1) * $renewals->perPage() }}</td>
                                <td>{{ optional($renewal->created_at)->format('d-m-Y / h:i A') ?: '-' }}</td>
                                <td>{{ optional($renewal->old_expires_at)->format('d-m-Y / h:i A') ?: '-' }}</td>
                                <td>{{ optional($renewal->new_expires_at)->format('d-m-Y / h:i A') ?: '-' }}</td>
                                <td>
                                    {{ optional($renewal->renewedBy)->name ?: '-' }}
                                    @if(optional($renewal->renewedBy)->email)
                                        <small>{{ $renewal->renewedBy->email }}</small>
                                    @endif
                                </td>
                                <td>{{ $renewal->remarks ?: '-' }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="text-center">No renewal history available.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                {{ $renewals->links() }}
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .plan-history-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
    }

    .summary-box {
        border: 1px solid #3a3d5d;
        background: #2b2d42;
        padding: 14px 16px;
        border-radius: 6px;
    }

    .summary-box span,
    .plan-history-table small {
        display: block;
        color: #c8c9d5;
        font-size: 12px;
        margin-bottom: 6px;
    }

    .summary-box strong {
        color: #fff;
        font-size: 15px;
    }

    .plan-history-table td,
    .plan-history-table th {
        vertical-align: middle;
    }

    .plan-history-table small {
        margin-top: 4px;
        margin-bottom: 0;
    }
</style>
@endpush
