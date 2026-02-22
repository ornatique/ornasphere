@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Generate Label List</h4>
        </div>

        <div class="card-body">



            <hr>


            <h5>Generated Labels List</h5>


            <table class="table table-bordered">

                <thead>
                    <tr>
                        <th>Select</th>
                        <th>Serial</th>
                        <th>QR Code</th>
                        <th>Barcode</th>
                    </tr>
                </thead>

                <tbody>

                    @foreach($sets as $set)

                    <tr>

                        <td>
                            <input type="checkbox" name="ids[]" value="{{ $set->id }}">
                        </td>

                        <td>{{ $set->qr_code }}</td>

                        <td>
                            <img src="{{ route('company.qr.image',$set->qr_code) }}" width="80">
                        </td>

                        <td>{{ $set->barcode }}</td>

                    </tr>

                    @endforeach

                </tbody>

            </table>

            <button class="btn btn-primary">Print Selected</button>


        </div>

    </div>

</div>

@endsection



@push("scripts")



<script>
    $(document).ready(function() {

        var table = $('#labelTable').DataTable({

            processing: true,
            serverSide: true,

            ajax: {
                url: "{{ route('company.label.print',$company->slug) }}",
                type: "GET",
                data: function(d) {
                    d.item_id = $('#item_id').val();
                }
            },

            columns: [

                {
                    data: 'DT_RowIndex',
                    name: 'DT_RowIndex',
                    orderable: false,
                    searchable: false
                },

                {
                    data: 'item_name',
                    name: 'item.item_name', // relationship search
                    orderable: true,
                    searchable: true
                },

                {
                    data: 'qr_code',
                    name: 'qr_code',
                    orderable: false,
                    searchable: false
                },

                {
                    data: 'serial',
                    name: 'serial', // ✅ FIXED HERE
                    orderable: true,
                    searchable: true
                }

            ]

        });

        $('#item_id').on('change', function() {
            table.ajax.reload();
        });

    });
</script>



@endpush