@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Other Charges List</h4>

            <a href="{{ route('company.other-charge.create', $company->slug) }}" class="btn btn-primary">
                + Add Other Charges
            </a>
        </div>
        <div class="card-body">

            <div class="table-responsive">
                <table id="otherChargeTable" class="table table-bordered table-striped">

                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Code</th>
                            <th>Default Amount</th>
                            <th>Action</th>
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
    $('#otherChargeTable').DataTable({

        processing: true,
        serverSide: true,

        ajax: "{{ route('company.other-charge.index',$company->slug) }}",

        columns: [{
                data: 'DT_RowIndex',
                name: 'id', // IMPORTANT FIX
                orderable: false,
                searchable: false
            },

            {
                data: 'other_charge'
            },
            {
                data: 'code'
            },
            {
                data: 'default_amount'
            },
            {
                data: 'action'
            }
        ]

    });
</script>
<script>
    $(document).on('click', '.deleteBtn', function() {

        if (!confirm('Are you sure to delete this record?')) {
            return;
        }

        var url = $(this).data('url');

        $.ajax({
            url: url,
            type: "DELETE",
            data: {
                _token: "{{ csrf_token() }}"
            },
            success: function(response) {

                $('#otherChargeTable').DataTable().ajax.reload();

                alert(response.message);

            },
            error: function() {
                alert('Delete failed');
            }
        });

    });
</script>

@endpush