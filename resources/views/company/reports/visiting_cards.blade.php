@extends('company_layout.admin')

@section('title', 'Visiting Cards Report')

@section('content')
<div class="content-wrapper">
    <!-- <div class="card mb-3">
        <div class="card-header">
            <div class="d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">Visiting Cards Report</h4>
                <a href="{{ route('company.reports.visiting-cards.create', auth()->user()->company->slug) }}" class="btn btn-primary">Create</a>
            </div>
        </div>
    </div> -->
    <div class="card">
        <div class="card-header visiting-card-header">
            <div class="visiting-card-header-row d-flex align-items-center flex-wrap gap-2 w-100">
                <h4 class="card-title mb-0">Visiting Cards Report</h4>
                <a href="{{ route('company.reports.visiting-cards.create', auth()->user()->company->slug) }}" class="btn btn-success create-btn-right">
                    <i class="typcn typcn-plus"></i> Create
                </a>
            </div>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end" id="visitingCardsFilterForm">
                <div class="col-md-3">
                    <label class="form-label mb-1">From Date</label>
                    <input type="date" name="from_date" id="from_date" class="form-control" value="{{ $fromDate }}">
                </div>
                <div class="col-md-3">
                    <label class="form-label mb-1">To Date</label>
                    <input type="date" name="to_date" id="to_date" class="form-control" value="{{ $toDate }}">
                </div>
                <div class="col-md-6 d-flex gap-2 justify-content-md-end flex-wrap">
                    <button class="btn btn-success" id="filterBtn" type="submit">Filter</button>
                    <a href="{{ route('company.reports.visiting-cards.index', auth()->user()->company->slug) }}" class="btn btn-secondary">Reset</a>
                    <button class="btn btn-info" id="exportExcelBtn" type="button">Excel</button>
                    <button class="btn btn-primary" id="exportPdfBtn" type="button">PDF</button>
                </div>
            </form>
        </div>
        <div class="card-body pt-0 px-3 pb-3">
            <div class="table-responsive">
                <table class="table table-bordered mb-0" id="visitingCardsDetailsTable">
                    <thead>
                        <tr>
                            <th>Sr No</th>
                            <th>Name</th>
                            <th>Mobile</th>
                            <th>Email</th>
                            <th>City</th>
                            <th>Pincode</th>
                            <th>Address</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .visiting-card-header .card-title,
    .visiting-card-header .btn {
        float: none !important;
    }

    .visiting-card-header .visiting-card-header-row {
        width: 100% !important;
        display: flex !important;
        justify-content: space-between !important;
        align-items: center !important;
    }

    .visiting-card-header .create-btn-right {
        margin-left: auto !important;
        display: inline-flex;
        align-items: center;
    }
</style>
@endpush

@push('scripts')
<script>
$(function () {
    const table = $('#visitingCardsDetailsTable').DataTable({
        processing: true,
        serverSide: true,
        searching: true,
        ordering: false,
        lengthChange: true,
        pageLength: 10,
        ajax: {
            url: "{{ route('company.reports.visiting-cards.index', $company->slug) }}",
            data: function (d) {
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', searchable: false },
            { data: 'name_fmt', name: 'name' },
            { data: 'mobile_fmt', name: 'mobile_no' },
            { data: 'email_fmt', name: 'email' },
            { data: 'city_fmt', name: 'city' },
            { data: 'pincode_fmt', name: 'pincode' },
            { data: 'address_fmt', name: 'address' },
            { data: 'created_at_fmt', name: 'created_at' }
        ],
        language: {
            emptyTable: 'No data found'
        }
    });

    $('#visitingCardsFilterForm').on('submit', function (e) {
        e.preventDefault();
        table.draw();
    });

    function queryParams() {
        return $.param({
            from_date: $('#from_date').val(),
            to_date: $('#to_date').val()
        });
    }

    $('#exportExcelBtn').on('click', function () {
        window.location.href = "{{ route('company.reports.visiting-cards.export.excel', $company->slug) }}?" + queryParams();
    });

    $('#exportPdfBtn').on('click', function () {
        window.location.href = "{{ route('company.reports.visiting-cards.export.pdf', $company->slug) }}?" + queryParams();
    });
});
</script>
@endpush
