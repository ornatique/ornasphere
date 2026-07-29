@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row mb-3">
        <div class="col-sm-8">
            <h3 class="mb-1 fw-bold">Company Dashboard</h3>
            <p class="mb-0 text-muted">Daily snapshot of sales, returns, approvals, and stock.</p>
        </div>
    </div>

    @if($canViewDashboardData ?? true)
    @php
        $spotSymbols = ['GOLD', 'SILVER', 'USD INR'];
        $allMetalRates = collect($metalRates['rates'] ?? []);
        $spotRates = $allMetalRates->filter(fn($rate) => in_array($rate['symbol'] ?? '', $spotSymbols, true))->values();
        $marketRates = $allMetalRates->reject(fn($rate) => in_array($rate['symbol'] ?? '', $spotSymbols, true))->values();
    @endphp
    <style>
        .metal-rates-grid {
            display: grid;
            grid-template-columns: minmax(0, 1.9fr) minmax(320px, 1fr);
            gap: 16px;
            align-items: start;
        }
        .city-rate-table {
            border: 2px solid rgba(255, 255, 255, 0.28);
            margin-bottom: 0;
        }
        .city-rate-table th,
        .city-rate-table td {
            border-color: rgba(255, 255, 255, 0.28) !important;
            vertical-align: middle;
            text-align: center;
        }
        .city-rate-table th {
            color: #ffffff;
            font-size: 18px;
            font-weight: 700;
            text-transform: uppercase;
        }
        .city-rate-table td {
            color: #f4f4f8;
            font-size: 17px;
            font-weight: 700;
            background: rgba(255, 255, 255, 0.03);
        }
        .city-rate-table td:first-child,
        .city-rate-table td:last-child {
            width: 50%;
        }
        .city-rate-value {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
            padding: 2px 8px;
        }
        .city-rate-symbol {
            display: inline-flex;
            flex-direction: column;
            align-items: center;
        }
        .city-rate-value.rate-up {
            background: #00a650;
            color: #ffffff;
        }
        .city-rate-value.rate-down {
            background: #fc424a;
            color: #ffffff;
        }
        .city-rate-subtitle {
            color: #aab2d5;
            font-size: 12px;
        }
        .spot-rate-table th,
        .spot-rate-table td {
            height: 44px;
        }
        .spot-rate-table .city-rate-symbol {
            text-transform: uppercase;
        }
        @media (max-width: 991px) {
            .metal-rates-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>

    <div class="row">
        <div class="col-12 grid-margin stretch-card">
            <div class="card">
                <div class="card-body">
                    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
                        <div>
                            <h4 class="card-title mb-1">Live City Wise Metal Rates</h4>
                            <p class="text-muted mb-0">Ahmedabad, Rajkot, and silver market rates</p>
                        </div>
                        <small class="text-muted">Updated: <span id="metalRatesUpdatedAt">{{ $metalRates['updated_at'] ?? '-' }}</span></small>
                    </div>

                    <div class="metal-rates-grid">
                        <div class="table-responsive">
                            <table class="table city-rate-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="marketRatesTableBody">
                                    @forelse($marketRates as $rate)
                                        @php
                                            $directionClass = $rate['direction'] === 'up' ? 'rate-up' : ($rate['direction'] === 'down' ? 'rate-down' : '');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="city-rate-symbol">
                                                    <span>{{ $rate['symbol'] }}</span>
                                                    <span class="city-rate-subtitle">{{ $rate['city'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($rate['available'])
                                                    <span class="city-rate-value {{ $directionClass }}">
                                                        {{ $rate['formatted_rate'] ?? number_format((float) $rate['rate'], (int) ($rate['decimals'] ?? 0), '.', '') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">Unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">{{ $metalRates['message'] ?? 'Metal rates are not available.' }}</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="table-responsive">
                            <table class="table city-rate-table spot-rate-table">
                                <thead>
                                    <tr>
                                        <th>Symbol</th>
                                        <th>Rate</th>
                                    </tr>
                                </thead>
                                <tbody id="spotRatesTableBody">
                                    @forelse($spotRates as $rate)
                                        @php
                                            $directionClass = $rate['direction'] === 'up' ? 'rate-up' : ($rate['direction'] === 'down' ? 'rate-down' : '');
                                        @endphp
                                        <tr>
                                            <td>
                                                <div class="city-rate-symbol">
                                                    <span>{{ $rate['symbol'] }}</span>
                                                </div>
                                            </td>
                                            <td>
                                                @if($rate['available'])
                                                    <span class="city-rate-value {{ $directionClass }}">
                                                        {{ $rate['formatted_rate'] ?? number_format((float) $rate['rate'], (int) ($rate['decimals'] ?? 0), '.', '') }}
                                                    </span>
                                                @else
                                                    <span class="text-muted">Unavailable</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="2" class="text-center text-muted">{{ $metalRates['message'] ?? 'Metal rates are not available.' }}</td>
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
    @else
    <style>
        .dashboard-access-state {
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: 18px;
            background:
                radial-gradient(circle at top right, rgba(255, 23, 68, 0.18), transparent 34%),
                linear-gradient(135deg, rgba(46, 50, 84, 0.98), rgba(29, 31, 54, 0.98));
            box-shadow: 0 18px 45px rgba(0, 0, 0, 0.24);
        }

        .dashboard-access-state::before {
            content: "";
            position: absolute;
            inset: 0;
            background: linear-gradient(120deg, rgba(255, 255, 255, 0.08), transparent 38%);
            pointer-events: none;
        }

        .dashboard-access-icon {
            width: 72px;
            height: 72px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 22px;
            background: linear-gradient(135deg, #ff1764, #1f7af8);
            color: #fff;
            font-size: 34px;
            box-shadow: 0 14px 30px rgba(31, 122, 248, 0.25);
        }

        .dashboard-access-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 12px;
            border-radius: 999px;
            background: rgba(255, 255, 255, 0.07);
            color: #cfd2ff;
            font-size: 13px;
            font-weight: 600;
        }

        .dashboard-access-wrap {
            min-height: calc(100vh - 310px);
            display: flex;
            align-items: center;
            justify-content: center;
        }
    </style>

    <div class="row dashboard-access-wrap">
        <div class="col-lg-9 col-xl-8 mx-auto">
            <div class="card dashboard-access-state">
                <div class="card-body p-5 position-relative">
                    <div class="row align-items-center">
                        <div class="col-md-auto mb-4 mb-md-0">
                            <div class="dashboard-access-icon">
                                <i class="typcn typcn-lock-closed"></i>
                            </div>
                        </div>
                        <div class="col">
                            <span class="dashboard-access-pill mb-3">
                                <i class="typcn typcn-info-large"></i>
                                Limited dashboard access
                            </span>
                            <h3 class="mb-3 fw-bold text-white">Dashboard data is not available for your account.</h3>
                            <p class="mb-2 text-muted">You can continue using the modules assigned to your role from the left menu.</p>
                            <p class="mb-0 text-muted">If you need dashboard reports, please contact your company admin to enable Dashboard View permission.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
@if($canViewDashboardData ?? true)
<script>
(function () {
    const ratesEndpoint = @json(route('company.dashboard.metal-rates', $company->slug ?? request()->route('slug')));
    const marketRatesBody = document.getElementById('marketRatesTableBody');
    const spotRatesBody = document.getElementById('spotRatesTableBody');
    const ratesUpdatedAt = document.getElementById('metalRatesUpdatedAt');
    const spotSymbols = ['GOLD', 'SILVER', 'USD INR'];
    let isRefreshingMetalRates = false;

    function escapeHtml(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (char) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[char];
        });
    }

    function rateRow(rate, showSubtitle) {
        const directionClass = rate.direction === 'up' ? 'rate-up' : (rate.direction === 'down' ? 'rate-down' : '');
        const rateValue = rate.available
            ? '<span class="city-rate-value ' + directionClass + '">' + escapeHtml(rate.formatted_rate ?? Number(rate.rate || 0).toFixed(Number(rate.decimals || 0))) + '</span>'
            : '<span class="text-muted">Unavailable</span>';
        const subtitle = showSubtitle
            ? '<span class="city-rate-subtitle">' + escapeHtml(rate.city) + '</span>'
            : '';

        return '<tr>' +
            '<td><div class="city-rate-symbol"><span>' + escapeHtml(rate.symbol) + '</span>' + subtitle + '</div></td>' +
            '<td>' + rateValue + '</td>' +
            '</tr>';
    }

    function renderMetalRates(payload) {
        if (!marketRatesBody || !spotRatesBody) return;

        const rates = Array.isArray(payload.rates) ? payload.rates : [];
        const spotRates = rates.filter(function (rate) {
            return spotSymbols.includes(rate.symbol || '');
        });
        const marketRates = rates.filter(function (rate) {
            return !spotSymbols.includes(rate.symbol || '');
        });

        if (ratesUpdatedAt) {
            ratesUpdatedAt.textContent = payload.updated_at || '-';
        }

        marketRatesBody.innerHTML = marketRates.length
            ? marketRates.map(function (rate) { return rateRow(rate, false); }).join('')
            : '<tr><td colspan="2" class="text-center text-muted">' + escapeHtml(payload.message || 'Market rates are not available.') + '</td></tr>';

        spotRatesBody.innerHTML = spotRates.length
            ? spotRates.map(function (rate) { return rateRow(rate, false); }).join('')
            : '<tr><td colspan="2" class="text-center text-muted">' + escapeHtml(payload.message || 'Gold, silver, and USD rates are not available.') + '</td></tr>';
    }

    async function refreshMetalRates() {
        if (!marketRatesBody || !spotRatesBody) return;
        if (isRefreshingMetalRates) return;

        isRefreshingMetalRates = true;

        try {
            const url = ratesEndpoint + (ratesEndpoint.includes('?') ? '&' : '?') + '_=' + Date.now();
            const response = await fetch(url, {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                cache: 'no-store'
            });

            if (!response.ok) return;

            renderMetalRates(await response.json());
        } catch (error) {
            // Keep the last visible rates if a short network failure happens.
        } finally {
            isRefreshingMetalRates = false;
        }
    }

    refreshMetalRates();
    setInterval(refreshMetalRates, 500);

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
@endif
@endpush
