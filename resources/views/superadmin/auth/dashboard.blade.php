@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row mb-3">
        <div class="col-sm-8">
            <h3 class="mb-1 fw-bold">Super Admin Dashboard</h3>
            <p class="mb-0 text-muted">Live SaaS overview across all companies.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Total Companies</h6>
                <h3 class="mb-0">{{ $totalCompanies }}</h3>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Active / Inactive</h6>
                <h3 class="mb-0">{{ $activeCompanies }} / {{ $inactiveCompanies }}</h3>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Total Users</h6>
                <h3 class="mb-0">{{ $totalUsers }}</h3>
                <small class="text-muted">Active users: {{ $activeUsers }}</small>
            </div></div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Open Approvals</h6>
                <h3 class="mb-0">{{ $openApprovals }}</h3>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-4 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Monthly Sales</h6>
                <h3 class="mb-0">Rs {{ number_format($monthlySales, 2) }}</h3>
            </div></div>
        </div>
        <div class="col-xl-4 col-md-6 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Monthly Returns</h6>
                <h3 class="mb-0">Rs {{ number_format($monthlyReturns, 2) }}</h3>
            </div></div>
        </div>
        <div class="col-xl-4 col-md-12 grid-margin stretch-card">
            <div class="card"><div class="card-body">
                <h6 class="text-muted mb-2">Monthly Net</h6>
                <h3 class="mb-0 {{ ($monthlySales - $monthlyReturns) >= 0 ? 'text-success' : 'text-danger' }}">Rs {{ number_format($monthlySales - $monthlyReturns, 2) }}</h3>
            </div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">New Companies Trend (Last 6 Months)</h4>
                    <canvas id="companyTrendChart" height="115"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-5 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Recent Companies</h4>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Plan</th>
                                    <th>Status</th>
                                    <th>Users</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentCompanies as $company)
                                    <tr>
                                        <td>{{ $company->name }}</td>
                                        <td>{{ $company->plan ?: '-' }}</td>
                                        <td>
                                            @if($company->status)
                                                <span class="badge bg-success">Active</span>
                                            @else
                                                <span class="badge bg-danger">Inactive</span>
                                            @endif
                                        </td>
                                        <td>{{ $company->users_count }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="text-center text-muted">No companies found.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
(function () {
    const el = document.getElementById('companyTrendChart');
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'bar',
        data: {
            labels: @json($labels),
            datasets: [{
                label: 'New Companies',
                data: @json($companyTrend),
                backgroundColor: 'rgba(0, 210, 91, 0.5)',
                borderColor: '#00d25b',
                borderWidth: 1.5,
                borderRadius: 6,
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: { color: '#cfcfe2' }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#9b9bb5' },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                },
                y: {
                    beginAtZero: true,
                    ticks: { color: '#9b9bb5', precision: 0 },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            }
        }
    });
})();
</script>
@endpush
