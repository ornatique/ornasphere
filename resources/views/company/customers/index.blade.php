@extends('company_layout.admin')
@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Persons List</h4>
            <div class="d-flex gap-2">
                <button type="button" id="exportExcelBtn" class="btn btn-success">Export Excel</button>
                <button type="button" id="exportPdfBtn" class="btn btn-danger">Export PDF</button>
                <a href="{{ route('company.customers.create', $company->slug) }}" class="btn btn-primary">
                    <i class="typcn typcn-plus-outline"></i>
                    Create Person
                </a>
            </div>
        </div>
        <div class="card-body">
            <div class="filter-panel mb-3">
                <div class="row align-items-end g-3 person-filter-row">
                    <div class="col-xl-4 col-lg-5 col-md-6 person-filter-select-wrap">
                        <label for="category_person_id" class="form-label">Category Person</label>
                        <select id="category_person_id" class="form-control person-filter-select searchable-person-select">
                            <option value="">All Category Persons</option>
                            @foreach($categoryPeople as $categoryPerson)
                                <option value="{{ $categoryPerson->id }}">{{ $categoryPerson->category_name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-3 person-filter-action-wrap">
                        <button type="button" id="filterBtn" class="btn btn-primary w-100 person-filter-action">Filter</button>
                    </div>
                    <div class="col-xl-2 col-lg-3 col-md-3 person-filter-action-wrap">
                        <button type="button" id="resetBtn" class="btn btn-secondary w-100 person-filter-action">Reset</button>
                    </div>
                </div>
            </div>

            <table class="table table-bordered" id="customers-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Name</th>
                        <th>Category Person</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>City</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</div>
@endsection

@push("scripts")
<script>
$(function () {
    if (window.jQuery && $.fn.select2) {
        $('#category_person_id').select2({
            theme: 'bootstrap4',
            width: '100%',
            minimumResultsForSearch: 0,
            placeholder: 'All Category Persons'
        });
    }

    const table = $('#customers-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.customers.index', $company->slug) }}",
            data: function (data) {
                data.category_person_id = $('#category_person_id').val();
            }
        },
        columns: [
            { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
            { data: 'name', name: 'name' },
            { data: 'category_person', name: 'category_person', orderable: false, searchable: false },
            { data: 'email', name: 'email' },
            { data: 'mobile_no', name: 'mobile_no' },
            { data: 'city', name: 'city' },
            { data: 'status', name: 'status', orderable: false, searchable: false },
            { data: 'action', name: 'action', orderable: false, searchable: false }
        ]
    });

    function filterQueryString() {
        const params = new URLSearchParams();
        const categoryPersonId = $('#category_person_id').val();

        if (categoryPersonId) {
            params.set('category_person_id', categoryPersonId);
        }

        const queryString = params.toString();
        return queryString ? '?' + queryString : '';
    }

    $('#filterBtn').on('click', function () {
        table.ajax.reload();
    });

    $('#resetBtn').on('click', function () {
        $('#category_person_id').val('').trigger('change.select2');
        table.ajax.reload();
    });

    $('#exportExcelBtn').on('click', function () {
        window.location.href = "{{ route('company.customers.export.excel', $company->slug) }}" + filterQueryString();
    });

    $('#exportPdfBtn').on('click', function () {
        window.location.href = "{{ route('company.customers.export.pdf', $company->slug) }}" + filterQueryString();
    });

    $(document).on('click', '.deleteBtn', function () {
        if (!confirm('Are you sure? Person will be set inactive (not deleted).')) return;

        $.ajax({
            url: $(this).data('url'),
            type: 'DELETE',
            data: { _token: "{{ csrf_token() }}" },
            success: function (resp) {
                table.ajax.reload();
                alert(resp.message || 'Person updated successfully');
            },
            error: function (xhr) {
                const msg = xhr.responseJSON && xhr.responseJSON.message ? xhr.responseJSON.message : 'Action failed';
                alert(msg);
            }
        });
    });
});
</script>
@endpush
