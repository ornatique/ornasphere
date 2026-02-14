@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">Generate Label List</h4>
        </div>

        <div class="card-body">


            <form method="POST"
                action="{{ route('company.label.generate',$company->slug) }}">

                @csrf

                <div class="row">

                    <div class="col-md-4">

                        <select name="item_id"
                            id="item_id"
                            class="form-select">

                            <option value="">
                                Select Item
                            </option>

                            @foreach($items as $item)

                            <option value="{{ $item->id }}">
                                {{ $item->item_name }}
                            </option>

                            @endforeach

                        </select>

                    </div>


                    <div class="col-md-3">

                        <input type="number"
                            name="qty"
                            class="form-control"
                            placeholder="Qty">

                    </div>


                    <div class="col-md-3">

                        <button class="btn btn-primary">
                            Generate
                        </button>

                    </div>

                </div>

            </form>


            <hr>


            <h5>Generated Labels List</h5>


            <table class="table table-bordered"
                id="labelTable">

                <thead>

                    <tr>

                        <th>ID</th>
                        <th>Item</th>
                        <th>QR</th>
                      
                        <th>Serial</th>

                    </tr>

                </thead>

            </table>


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