@extends('layout.admin')

@section('content')
<div class="content-wrapper company-validity-report">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h4 class="card-title mb-1">Company Users & Plan Validity</h4>
                <p class="mb-0 text-muted">Company and branch details, admin contacts, user counts, and plan validity.</p>
            </div>
            <a href="{{ route('superadmin.companies.index') }}" class="btn btn-secondary">Back to Companies</a>
        </div>
        <div class="card-body">
            <form method="GET" action="{{ route('superadmin.companies.validity-report') }}" class="validity-filter-row mb-4">
                <div>
                    <label for="reportPlan">Plan</label>
                    <select name="plan" id="reportPlan" class="form-control">
                        <option value="">All plans</option>
                        <option value="trial" @selected(($filters['plan'] ?? '') === 'trial')>10-day Trial</option>
                        <option value="yearly" @selected(($filters['plan'] ?? '') === 'yearly')>Yearly</option>
                        @foreach([2, 3, 4, 5] as $years)
                            <option value="{{ $years }}_years" @selected(($filters['plan'] ?? '') === $years . '_years')>{{ $years }} Years</option>
                        @endforeach
                        <option value="custom" @selected(($filters['plan'] ?? '') === 'custom')>Custom Dates</option>
                    </select>
                </div>
                <div>
                    <label for="reportValidity">Plan validity</label>
                    <select name="validity" id="reportValidity" class="form-control">
                        <option value="">All</option>
                        <option value="active" @selected(($filters['validity'] ?? '') === 'active')>Active</option>
                        <option value="expired" @selected(($filters['validity'] ?? '') === 'expired')>Expired</option>
                    </select>
                </div>
                <div>
                    <label for="expiryFrom">Expiry from</label>
                    <input type="date" name="expiry_from" id="expiryFrom" value="{{ $filters['expiry_from'] ?? '' }}" class="form-control">
                </div>
                <div>
                    <label for="expiryTo">Expiry to</label>
                    <input type="date" name="expiry_to" id="expiryTo" value="{{ $filters['expiry_to'] ?? '' }}" class="form-control">
                </div>
                <div class="validity-filter-actions">
                    <button type="submit" class="btn btn-success">Filter</button>
                    <a href="{{ route('superadmin.companies.validity-report') }}" class="btn btn-secondary">Reset</a>
                    <a href="{{ route('superadmin.companies.validity-report.excel', request()->query()) }}" class="btn btn-primary">Export Excel</a>
                    <a href="{{ route('superadmin.companies.validity-report.pdf', request()->query()) }}" class="btn btn-info">Export PDF</a>
                </div>
            </form>

            @if($errors->any())
                <div class="alert alert-danger">{{ $errors->first() }}</div>
            @endif

            @php
                $companyRowsCount = $rows->where('type', 'Company')->count();
                $branchRowsCount = $rows->where('type', 'Branch')->count();
            @endphp
            <p class="text-muted">Companies: {{ $companyRowsCount }} &middot; Branches: {{ $branchRowsCount }} &middot; Total locations: {{ $rows->count() }}</p>
            <div class="table-responsive">
                <table id="companyValidityTable" class="table table-bordered table-hover text-nowrap w-100">
                    <thead>
                        <tr>
                            <th>Company / Branch</th><th>Type</th><th>Parent Company</th><th>Branches</th><th>Company Email</th>
                            <th>Admin Name</th><th>Admin Email</th><th>Users</th><th>Active Users</th><th>User Limit</th>
                            <th>Plan</th><th>Plan Start</th><th>Plan Expiry</th><th>Plan Validity</th><th>Company Access</th>
                            <th>City</th><th>State</th><th>Postcode</th><th>Country</th><th>Created At</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($rows as $row)
                            <tr>
                                <td>{{ $row['name'] }}</td><td>{{ $row['type'] }}</td><td>{{ $row['parent_name'] }}</td><td>{{ $row['branch_count'] }}</td>
                                <td>{{ $row['email'] }}</td><td>{{ $row['admin_name'] }}</td><td>{{ $row['admin_email'] }}</td>
                                <td>{{ $row['users'] }}</td><td>{{ $row['active_users'] }}</td><td>{{ $row['user_limit'] }}</td>
                                <td>{{ $row['plan'] }}</td><td>{{ $row['started_at'] }}</td><td>{{ $row['expires_at'] }}</td>
                                <td><span class="badge {{ $row['validity_value'] === 'active' ? 'badge-success' : 'badge-danger' }}">{{ $row['validity'] }}</span></td>
                                <td>{{ $row['company_access'] }}</td><td>{{ $row['city'] }}</td><td>{{ $row['state'] }}</td>
                                <td>{{ $row['postcode'] }}</td><td>{{ $row['country'] }}</td><td>{{ $row['created_at'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="20" class="text-center">No companies match these filters.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .company-validity-report .card-header { padding: 20px 24px; }
    .company-validity-report .card-body { padding: 24px; }
    .company-validity-report label { display:block; margin-bottom:7px; color:#cbd3df; }
    .company-validity-report .form-control { min-height:42px; }
    .company-validity-report table th { background:#303249; color:#d4dcf3; }
    .company-validity-report table th,.company-validity-report table td { vertical-align:middle; }
    .company-validity-report .badge { padding:6px 9px; }
    .company-validity-report .validity-filter-row { display:grid; grid-template-columns:minmax(160px,1.2fr) minmax(140px,.9fr) repeat(2,minmax(145px,1fr)) auto; gap:12px; align-items:end; }
    .company-validity-report .validity-filter-actions { display:flex; flex-wrap:nowrap; gap:8px; }
    .company-validity-report .validity-filter-actions .btn { min-height:42px; white-space:nowrap; }
    .company-validity-report .dataTables_wrapper .dataTables_filter input,
    .company-validity-report .dataTables_wrapper .dataTables_length select { min-height:36px; border:1px solid #565a7a; border-radius:6px; background:#303149; color:#fff; padding:5px 9px; }
    .company-validity-report .dataTables_wrapper .dataTables_filter input { margin-left:8px; }
    .company-validity-report .dataTables_wrapper .dataTables_info,
    .company-validity-report .dataTables_wrapper .dataTables_paginate { padding-top:14px; }
    @media(max-width:1300px) { .company-validity-report .validity-filter-row { grid-template-columns:repeat(4,minmax(150px,1fr)); } .company-validity-report .validity-filter-actions { grid-column:1/-1; } }
    @media(max-width:700px) { .company-validity-report .validity-filter-row { grid-template-columns:repeat(2,minmax(0,1fr)); } .company-validity-report .validity-filter-actions { grid-column:1/-1; flex-wrap:wrap; } }
    @media(max-width:460px) { .company-validity-report .validity-filter-row { grid-template-columns:minmax(0,1fr); } }
</style>
@endpush

@push('scripts')
<script>
    $(function () {
        $('#reportPlan, #reportValidity').on('change', function () {
            $(this).closest('form').trigger('submit');
        });

        if ($.fn.DataTable) {
            $('#companyValidityTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, 'All']],
                order: [],
                autoWidth: false,
                scrollX: true,
                responsive: false,
                columnDefs: [{ targets: [6, 7, 8], className: 'text-end' }],
                language: { emptyTable: 'No companies match these filters.' }
            });
        }
    });
</script>
@endpush
