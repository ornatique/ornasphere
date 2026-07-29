@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title">Category Person List</h4>

            <a href="{{ route('company.category-persons.create', $company->slug) }}" class="btn btn-primary">
                + Add Category Person
            </a>
        </div>

        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" id="categoryPersonTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Category Name</th>
                            <th>Created At</th>
                            <th>Action</th>
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
    #categoryPersonTable .action-buttons {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
</style>
@endpush

@push('scripts')
<script>
    $(function () {
        $('#categoryPersonTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{{ route('company.category-persons.index', $company->slug) }}",
            order: [[1, 'asc']],
            columns: [
                { data: 'DT_RowIndex', name: 'DT_RowIndex', orderable: false, searchable: false },
                { data: 'category_name', name: 'category_name' },
                { data: 'created_at_display', name: 'created_at', searchable: false },
                { data: 'action', name: 'action', orderable: false, searchable: false }
            ]
        });
    });
</script>
@endpush
