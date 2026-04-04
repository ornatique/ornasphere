@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">
        {{-- HEADER --}}
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Item Sets</h4>

            <div>
                <a href="{{ route('company.item_sets.index', $company->slug) }}" class="btn btn-primary">
                    Create Label Item
                </a>
            </div>
        </div>

        {{-- FILTER --}}
        <div class="card-body border-bottom">
            <div class="row">
                <div class="col-md-3">
                    <label>From Date</label>
                    <input type="date" id="from_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>To Date</label>
                    <input type="date" id="to_date" class="form-control">
                </div>

                <div class="col-md-3">
                    <label>Item</label>
                    <select id="item_id" class="form-select">
                        <option value="">All Items</option>
                        @foreach($items as $item)
                            <option value="{{ $item->id }}">{{ $item->item_name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="col-md-3 d-flex align-items-end">
                    <button class="btn btn-primary w-100" id="filterBtn">
                        Apply Filter
                    </button>
                </div>

            </div>
        </div>

        {{-- TABLE --}}
        <div class="card-body">

            <table class="table table-bordered" id="itemset-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Date</th>
                        <th>Item</th>
                        <th>Label Code</th>
                        <th>Gross Wt</th>
                        <th>Other Wt</th>
                        <th>Net Wt</th>
                        <th>Qty Pcs</th>
                        <th>Print Date Time</th>
                        <th>Action</th>
                    </tr>
                </thead>
            </table>

        </div>
    </div>

</div>

<div class="modal fade" id="editModal">
    <div class="modal-dialog modal-lg">
        <div class="modal-content bg-dark text-white border-0">

            <div class="modal-header border-bottom">
                <h5 class="modal-title">Edit Item Set</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="edit_id">

                <div class="row">

                    <div class="col-md-6 mb-3">
                        <label>Gross Weight</label>
                        <input type="text" id="gross_weight" class="form-control  text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Net Weight</label>
                        <input type="text" id="net_weight" class="form-control text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Other</label>
                        <input type="text" id="other" class="form-control  text-white border-0">
                    </div>

                    <div class="col-md-6 mb-3">
                        <label>Size</label>
                        <input type="text" id="size" class="form-control  text-white border-0">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label>HUID</label>
                        <input type="text" id="huid" class="form-control  text-white border-0">
                    </div>

                </div>

            </div>

            <div class="modal-footer border-top">
                <button class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                <button class="btn btn-success" id="updateBtn">
                    Update
                </button>
            </div>

        </div>
    </div>
</div>
@endsection


@push('scripts')
<script>
    let table = $('#itemset-table').DataTable({
        processing: true,
        serverSide: true,
        ajax: {
            url: "{{ route('company.list_itemset', $company->slug) }}",
            data: function(d) {
                d.item_id = $('#item_id').val();
                d.from_date = $('#from_date').val();
                d.to_date = $('#to_date').val();
            }
        },
                columns: [
            {
                data: 'DT_RowIndex',
                name: 'id',
                orderable: false,
                searchable: false
            },
            {
                data: 'date',
                name: 'date',
                searchable: false
            },
            {
                data: 'item_name',
                name: 'item_name',
                searchable: true
            },
            {
                data: 'qr_code',
                name: 'qr_code',
                searchable: true
            },
            {
                data: 'gross_weight',
                name: 'gross_weight',
                searchable: true
            },
            {
                data: 'other_weight',
                name: 'other_weight',
                searchable: true
            },
            {
                data: 'net_weight',
                name: 'net_weight',
                searchable: true
            },
            {
                data: 'qty_pcs',
                name: 'qty_pcs',
                searchable: false
            },
            {
                data: 'printed_at',
                name: 'printed_at',
                searchable: false
            },
            {
                data: 'action',
                name: 'action',
                orderable: false,
                searchable: false
            }
        ]
    });


    // 🔍 FILTER
    $('#filterBtn').click(function() {
        table.draw();
    });


    // 🗑 DELETE
    $(document).on('click', '.deleteBtn', function() {

        let url = $(this).data('url');

        if (confirm('Delete this item?')) {
            $.ajax({
                url: url,
                type: "DELETE",
                data: {
                    _token: "{{ csrf_token() }}"
                },
                success: function() {
                    table.draw();
                }
            });
        }
    });

    $(document).on('click', '.editBtn', function() {

        let url = $(this).data('url');

        $.get(url, function(data) {

            $('#edit_id').val(data.id);
            $('#gross_weight').val(data.gross_weight);
            $('#net_weight').val(data.net_weight);
            $('#other').val(data.other);
            $('#size').val(data.size);
            $('#huid').val(data.HUID);

            $('#editModal').modal('show');
        });

    });

    $('#updateBtn').click(function() {

        let id = $('#edit_id').val();

        $.post("{{ url('company/'.$company->slug.'/itemsets') }}/" + id + "/update", {
            _token: "{{ csrf_token() }}",
            gross_weight: $('#gross_weight').val(),
            net_weight: $('#net_weight').val(),
            other: $('#other').val(),
            size: $('#size').val(),
            huid: $('#huid').val(),
        }, function() {

            $('#editModal').modal('hide');
            table.draw();

        });

    });
</script>
@endpush

