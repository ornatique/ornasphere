@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Label Config List</h4>

            <a href="{{ route('company.label_config.create', $company->slug) }}" class="btn btn-primary">
                + Add Label Config
            </a>
        </div>

        <div class="card-body">
            <p class="text-muted">New records are created in {{ $company->name }}. Each existing record is managed in its own branch.</p>

            <div class="table-responsive">

                <table id="labelConfigTable" class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Item Name</th>
                            <!-- <th>QR Code</th> -->

                            <th>Prefix</th>

                            <th>Company / Branch</th><th width="150">Action</th>
                        </tr>
                    </thead>

                </table>

            </div>

        </div>
    </div>

</div>
@endsection
@push('scripts')
<script>
    $(document).ready(function() {

        $('#labelConfigTable').DataTable({

            processing: true,
            serverSide: true,

            ajax: "{!! route('company.label_config.index', array_merge(['slug'=>$company->slug], request()->only('management_branches'))) !!}",

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
                // {
                //     data: 'qr_code',
                //     name: 'qr_code',
                //     orderable: false,
                //     searchable: false
                // },

                {
                    data: 'prefix',
                    name: 'prefix'
                },
                { data: 'company_name', orderable: false, searchable: false },
                {
                    data: 'action',
                    name: 'action',
                    orderable: false,
                    searchable: false
                }
            ]

        });

    });
</script>
@endpush
@section('css')
