@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="card-title">Approval Return</h4>
        </div>

        <div class="card-body">

            <form id="returnForm">
                @csrf

                <input type="hidden" name="approval_id" value="{{ $approval->id }}">

                <div class="mb-3">
                    <label>Voucher Remarks</label>
                    <textarea name="voucher_remarks" class="form-control" rows="2" placeholder="Enter remarks for this return"></textarea>
                </div>

                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll"></th>
                            <th>HUID</th>
                            <th>QR</th>
                            <th>Available Qty</th>
                            <th>Return Qty</th>
                            <th>Gross</th>
                            <th>Net</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                        @foreach($approval->items as $item)

                        <tr>
                            <td>
                                <input type="checkbox"
                                    name="items[]"
                                    value="{{ $item->id }}"
                                    class="itemCheckbox">
                            </td>

                            <td>{{ optional($item->itemSet ?? $item->legacyItemSet)->HUID ?? $item->huid }}</td>
                            <td>{{ optional($item->itemSet ?? $item->legacyItemSet)->qr_code ?? $item->qr_code }}</td>
                            <td>{{ (int) $item->available_qty }}</td>
                            <td>
                                <input type="number"
                                    name="quantities[{{ $item->id }}]"
                                    class="form-control return-qty"
                                    min="1"
                                    max="{{ (int) $item->available_qty }}"
                                    value="{{ (int) $item->available_qty }}"
                                    style="width: 100px">
                            </td>

                            <td>{{ $item->gross_weight }}</td>
                            <td>{{ $item->net_weight }}</td>

                            <td>
                                <span class="badge bg-warning">Approval</span>
                            </td>
                        </tr>

                        @endforeach

                    </tbody>
                </table>

                <button type="button" id="saveReturn"
                    class="btn btn-danger">
                    Return Selected
                </button>

            </form>

        </div>

    </div>

</div>
@endsection


@push('scripts')
<script>
    $('#selectAll').click(function() {
        $('.itemCheckbox').prop('checked', this.checked);
    });

    $('#saveReturn').click(function() {
        const selected = $('.itemCheckbox:checked');
        if (selected.length === 0) {
            alert('Select items');
            return;
        }

        let invalidQty = false;
        selected.each(function() {
            const row = $(this).closest('tr');
            const input = row.find('.return-qty');
            const qty = Number(input.val());
            const max = Number(input.attr('max'));
            if (!Number.isInteger(qty) || qty < 1 || qty > max) {
                invalidQty = true;
                input.addClass('is-invalid');
            } else {
                input.removeClass('is-invalid');
            }
        });
        if (invalidQty) {
            alert('Enter a whole return quantity between 1 and the available quantity for each selected item.');
            return;
        }

        const button = $(this).prop('disabled', true);
        $.ajax({
            url: "{{ route('company.approval.return.store', $company->slug) }}",
            type: 'POST',
            data: $('#returnForm').serialize(),
            success: function(response) {
                if (response && response.success === false) {
                    alert(response.error || response.message || 'Return could not be saved.');
                    return;
                }
                alert(response.message || 'Return saved successfully.');
                window.location.href = "{{ route('company.approval.index', $company->slug) }}";
            },
            error: function(xhr) {
                const message = xhr.responseJSON?.message
                    || xhr.responseJSON?.error
                    || 'Return could not be saved. Please refresh and try again.';
                alert(message);
            },
            complete: function() {
                button.prop('disabled', false);
            }
        });
    });
</script>
@endpush
