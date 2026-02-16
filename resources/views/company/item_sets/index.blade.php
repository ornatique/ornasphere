@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="card-title">Item Sets Grid</h4>
        </div>

        <div class="card-body">

            {{-- ITEM SELECT --}}
            <div class="row mb-3">

                <div class="col-md-4">

                    <div class="form-group">

                        <label>Select Item</label>

                        <select id="itemSelect" class="form-select">

                            <option value="">Select Item</option>

                            @foreach($items as $item)

                            <option value="{{ $item->id }}">
                                {{ $item->item_name }}
                            </option>

                            @endforeach

                        </select>

                    </div>

                </div>

                <div class="col-md-4">
                    <div class="form-group">
                        <label>Carat</label>
                        <input type="text"
                            id="carat"
                            class="form-control mb-3"
                            placeholder=""
                            required>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label>Purity</label>
                        <input type="text"
                            id="purity"
                            class="form-control mb-3"
                            placeholder=""
                            required>
                    </div>
                </div>

            </div>


            {{-- GRID TABLE --}}
            <div style="height:500px; overflow-y:auto;" id="gridContainer">

                <table class="table table-bordered table-striped">

                    <thead>

                        <tr>

                            <th width="120">Gross Weight</th>

                            <th width="120">Other</th>

                            <th width="120">Net Weight</th>

                            <th width="120">Labour Rate</th>

                            <th width="120">Labour Amount</th>

                            <th width="120">Size</th>

                            <th width="150">HUID</th>

                        </tr>

                    </thead>

                    <tbody id="setsBody">

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

@endsection


@push("scripts")



<script>
    let itemId = null;

    let offset = 0;

    let loading = false;


    //////////////////////////////////////////////////////
    // ITEM CHANGE LOAD FIRST 10 ROWS
    //////////////////////////////////////////////////////

    $('#itemSelect').change(function() {

        itemId = $(this).val();

        offset = 0;

        $('#setsBody').html('');

        loadMore();

    });


    //////////////////////////////////////////////////////
    // LOAD MORE FUNCTION
    //////////////////////////////////////////////////////

    function loadMore() {

        if (!itemId) return;

        if (loading) return;

        loading = true;

        $.get(
            "{{ route('company.item_sets.load',$company->slug) }}", {
                offset: offset,
                item_id: itemId
            },
            function(rows) {

                //////////////////////////////////////////////////////
                // ADD EXISTING ROWS
                //////////////////////////////////////////////////////

                rows.forEach(addRow);

                offset += rows.length;

                //////////////////////////////////////////////////////
                // ALWAYS KEEP MINIMUM 10 ROWS
                //////////////////////////////////////////////////////

                let currentRows = $('#setsBody tr').length;

                if (currentRows < 10) {

                    let remaining = 10 - currentRows;

                    for (let i = 0; i < remaining; i++) {

                        addEmptyRow();

                    }

                }

                loading = false;

            }
        );

    }



    //////////////////////////////////////////////////////
    // ADD EXISTING ROW
    //////////////////////////////////////////////////////

    function addRow(row) {

        $('#setsBody').append(`

        <tr data-id="${row.id}">

            <td contenteditable="true" class="cell" data-column="gross_weight">${row.gross_weight ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="other">${row.other ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="net_weight">${row.net_weight ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate">${row.sale_labour_rate ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount">${row.sale_labour_amount ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="size">${row.size ?? ''}</td>

            <td contenteditable="true" class="cell" data-column="HUID">${row.HUID ?? ''}</td>

        </tr>

    `);

    }


    //////////////////////////////////////////////////////
    // ADD EMPTY ROW
    //////////////////////////////////////////////////////

    function addEmptyRow() {

        $('#setsBody').append(`

        <tr data-id="">

            <td contenteditable="true" class="cell" data-column="gross_weight"></td>

            <td contenteditable="true" class="cell" data-column="other"></td>

            <td contenteditable="true" class="cell" data-column="net_weight"></td>

            <td contenteditable="true" class="cell" data-column="sale_labour_rate"></td>

            <td contenteditable="true" class="cell" data-column="sale_labour_amount"></td>

            <td contenteditable="true" class="cell" data-column="size"></td>

            <td contenteditable="true" class="cell" data-column="HUID"></td>

        </tr>

    `);

    }


    //////////////////////////////////////////////////////
    // AUTO SAVE CELL
    //////////////////////////////////////////////////////

    $(document).on('blur', '.cell', function() {

        let tr = $(this).closest('tr');

        let id = tr.attr('data-id');

        let column = $(this).data('column');

        let value = $(this).text();

        $.post(
            "{{ route('company.item_sets.saveCell',$company->slug) }}", {
                _token: "{{ csrf_token() }}",

                id: id,

                item_id: itemId,

                column: column,

                value: value
            },
            function(res) {

                // VERY IMPORTANT LINE
                tr.attr('data-id', res.id);

            }
        );

    });



    //////////////////////////////////////////////////////
    // SCROLL LOAD MORE
    //////////////////////////////////////////////////////

    $('#gridContainer').scroll(function() {

        let div = $(this)[0];

        if (div.scrollTop + div.clientHeight >= div.scrollHeight - 10) {
            loadMore();
        }

    });
</script>






<script>
    var urlTemplate = "{{ route('company.get-item-details', [$company->slug, ':id']) }}";

    $('#itemSelect').on('change', function() {

        var itemId = $(this).val();

        if (itemId) {
            var url = urlTemplate.replace(':id', itemId);

            $.get(url, function(res) {

                if (res.status) {
                    $('#carat').val(res.carat);
                    $('#purity').val(res.purity);
                }

            });
        }

    });
</script>


@endpush