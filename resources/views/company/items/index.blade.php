@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">Item List</h4>

                    <a href="{{ route('company.items.create', $company->slug) }}"
                        class="btn btn-primary">
                        + Create Item
                    </a>
                </div>

                <div class="card-body">
            @include('company_layout.item_management_exports')
            <p class="text-muted">New records are created in {{ $company->name }}. Each existing record is managed in its own branch.</p>
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped"
                            id="itemTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Item Name</th>
                                    <th>Item Code</th>
                                    <th>Metal</th>
                                    <th>Labour Type</th>
                                    <!-- <th>Tax Type</th> -->
                                    <!-- <th>Status</th> -->
                                    <th>Company / Branch</th><th width="150">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{-- Yajra will load --}}
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
    $(document).ready(function() {

        let table = $('#itemTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: "{!! route('company.items.data', array_merge(['slug'=>$company->slug], request()->only('management_branches'))) !!}",
            columns: [{
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false, // ✅ IMPORTANT
                    searchable: false // ✅ IMPORTANT
                },
                {
                    data: 'item_name',
                    name: 'item_name'
                },
                {
                    data: 'item_code',
                    name: 'item_code'
                },
                {
                    data: 'metal',
                    name: 'metal'
                },
                {
                    data: 'labour_type',
                    name: 'labour_type'
                },
                // {
                //     data: 'tax_type',
                //     name: 'tax_type'
                // },
                // {
                //     data: 'status',
                //     name: 'status',
                //     orderable: false,
                //     searchable: false
                // },
                { data: 'company_name', orderable: false, searchable: false },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                },
            ]
        });


        // ================= DELETE ITEM =================
        $(document).on('click', '.deleteItem', function() {

            if (!confirm('Are you sure you want to delete this item?')) {
                return;
            }

            let url = $(this).data('url');

            $.ajax({
                url: url,
                type: "DELETE",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function(response) {
                    table.ajax.reload(null, false);
                },
                error: function(xhr) {
                    alert(xhr.responseJSON?.message || 'Unable to delete item.');
                }
            });

        });

    });
</script>

@endpush
