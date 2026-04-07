@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row mb-3">
        <div class="col-sm-8">
            <h3 class="mb-1 fw-bold">Company Dashboard</h3>
            <p class="mb-0 text-muted">Daily snapshot of sales, returns, approvals, and stock.</p>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Today Sales</h6>
                    <h3 class="mb-0">Rs {{ number_format($salesToday, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Month Sales</h6>
                    <h3 class="mb-0">Rs {{ number_format($salesMonth, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Month Returns</h6>
                    <h3 class="mb-0">Rs {{ number_format($returnsMonth, 2) }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Net This Month</h6>
                    <h3 class="mb-0 {{ $netMonth >= 0 ? 'text-success' : 'text-danger' }}">Rs {{ number_format($netMonth, 2) }}</h3>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Open Approvals</h6>
                    <h3 class="mb-0">{{ $approvalsOpen }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Pending Approval Items</h6>
                    <h3 class="mb-0">{{ $pendingApprovalItems }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Labels In Stock</h6>
                    <h3 class="mb-0">{{ $labelsInStock }}</h3>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h6 class="text-muted mb-2">Users / Customers</h6>
                    <h3 class="mb-0">{{ $users }} / {{ $customersCount }}</h3>
                    <small class="text-muted">Active customers: {{ $activeCustomersCount }}</small>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-7 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Monthly Trend (Last 6 Months)</h4>
                    <canvas id="companyMonthlyChart" height="120"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-5 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <h4 class="card-title mb-3">Recent Activity</h4>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>No</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentActivity as $row)
                                    <tr>
                                        <td>{{ $row['type'] }}</td>
                                        <td>{{ $row['number'] ?? '-' }}</td>
                                        <td>{{ $row['customer'] ?? '-' }}</td>
                                        <td>{{ !empty($row['date']) ? \Carbon\Carbon::parse($row['date'])->format('d-m-Y') : '-' }}</td>
                                        <td class="text-end">{{ number_format((float) ($row['amount'] ?? 0), 2) }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5" class="text-center text-muted">No activity found.</td>
                                    </tr>
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
    const el = document.getElementById('companyMonthlyChart');
    if (!el || typeof Chart === 'undefined') return;

    new Chart(el, {
        type: 'line',
        data: {
            labels: @json($monthlyLabels),
            datasets: [
                {
                    label: 'Sales',
                    data: @json($monthlySales),
                    borderColor: '#00d25b',
                    backgroundColor: 'rgba(0,210,91,0.15)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                },
                {
                    label: 'Returns',
                    data: @json($monthlyReturns),
                    borderColor: '#fc424a',
                    backgroundColor: 'rgba(252,66,74,0.10)',
                    borderWidth: 2,
                    tension: 0.35,
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            plugins: {
                legend: {
                    labels: {
                        color: '#cfcfe2'
                    }
                }
            },
            scales: {
                x: {
                    ticks: { color: '#9b9bb5' },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                },
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: '#9b9bb5',
                        callback: function (value) { return 'Rs ' + value; }
                    },
                    grid: { color: 'rgba(255,255,255,0.06)' }
                }
            }
        }
    });
})();
</script>
@endpush
