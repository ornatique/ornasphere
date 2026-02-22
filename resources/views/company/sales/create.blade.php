@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <form method="POST" action="{{ route('company.sales.store', ['slug' => $company->slug]) }}">
        @csrf

        <div class="card">

            <div class="card-header">
                <h4>Create Sale</h4>
            </div>

            <div class="card-body">

                {{-- ================= CUSTOMER & ITEM ================= --}}
                <div class="row mb-3">

                    {{-- CUSTOMER --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Customer</label>
                            <select name="customer_id" class="form-select" required>
                                <option value="">Select Customer</option>
                                @foreach($customers as $customer)
                                <option value="{{ $customer->id }}">
                                    {{ $customer->name }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    {{-- ITEM SELECT --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Select Item</label>
                            <select id="itemset_select" class="form-select select2">
                                <option value="">Search Item...</option>

                                @foreach($itemsets as $item)
                                <option value="{{ $item->id }}"
                                    data-name="{{ $item->item->item_name ?? '' }}"
                                    data-code="{{ $item->qr_code ?? '' }}"
                                    data-gross="{{ $item->gross_weight }}"
                                    data-net="{{ $item->net_weight }}"
                                    data-purity="{{ $item->item->outward_purity ?? '' }}"
                                    data-amount="{{ $item->other }}">

                                    {{ $item->item->item_name ?? '' }} - {{ $item->qr_code ?? '' }}
                                </option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                </div>

                {{-- ================= SALE TABLE ================= --}}
                <div class="table-responsive">
                    <table class="table table-bordered">

                        <thead class="table-light">
                            <tr>
                                <th>Item</th>
                                <th>Gross</th>
                                <th>Net</th>
                                <th>Purity</th>

                                <th>Amount</th>
                                <th>Action</th>
                            </tr>
                        </thead>

                        <tbody id="saleBody"></tbody>

                        <tfoot>
                            <tr>
                                <th colspan="5" class="text-end">Grand Total :</th>
                                <th colspan="2">
                                    ₹ <span id="grandTotal">0.00</span>
                                </th>
                            </tr>
                        </tfoot>

                    </table>
                </div>

            </div>

            <div class="card-footer text-end">
                <button class="btn btn-primary">
                    Save Sale
                </button>
            </div>

        </div>
    </form>

</div>

@endsection



@push('scripts')

<script>
    $(document).ready(function() {

        // Initialize Select2
        $('#itemset_select').select2({
            theme: "bootstrap4",
            placeholder: "Search Item...",
            allowClear: true,
            width: '100%'
        });

    });


    // ================= ADD ITEM =================

    $('#itemset_select').on('change', function() {

        let selected = $(this).find(':selected');

        let id = selected.val();
        let name = selected.data('name');
        let code = selected.data('code');
        let gross = selected.data('gross') || 0;
        let net = selected.data('net') || 0;
        let purity = selected.data('purity') || 0;
        let fine = selected.data('fine') || 0;
        let amount = parseFloat(selected.data('amount')) || 0;

        if (!id) return;

        // Prevent duplicate
        if ($('input[name="items[]"][value="' + id + '"]').length) {
            alert("Item already added!");
            return;
        }

        let row = `
        <tr>
            <td>
                ${name} - ${code}
                <input type="hidden" name="items[]" value="${id}">
            </td>

            <td>${gross}</td>

            <td>
                <input type="number" step="0.001"
                    name="net_weight[]"
                    class="form-control netWeight"
                    value="${net}">
            </td>

            <td>
                <input type="number" step="0.01"
                    name="purity[]"
                    class="form-control purity"
                    value="${purity}">
            </td>

           

            <td>
                <input type="number" step="0.01"
                    name="amount[]"
                    class="form-control rowAmount"
                    value="${amount.toFixed(2)}">
            </td>

            <td>
                <button type="button"
                    class="btn btn-danger btn-sm removeRow">
                    Remove
                </button>
            </td>
        </tr>
        `;

        $('#saleBody').append(row);

        calculateGrandTotal();

        $(this).val(null).trigger('change');
    });


    // ================= AUTO FINE CALCULATION =================

    $(document).on('input', '.netWeight, .purity', function() {

        let row = $(this).closest('tr');

        let net = parseFloat(row.find('.netWeight').val()) || 0;
        let purity = parseFloat(row.find('.purity').val()) || 0;

        let fine = (net * purity) / 100;

        row.find('.fineWeight').val(fine.toFixed(3));
    });


    // ================= GRAND TOTAL =================

    function calculateGrandTotal() {

        let total = 0;

        $('.rowAmount').each(function() {
            total += parseFloat($(this).val()) || 0;
        });

        $('#grandTotal').text(total.toFixed(2));
    }

    $(document).on('input', '.rowAmount', function() {
        calculateGrandTotal();
    });

    $(document).on('click', '.removeRow', function() {
        $(this).closest('tr').remove();
        calculateGrandTotal();
    });
</script>

@endpush