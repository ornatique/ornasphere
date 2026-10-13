@php
    $opening = isset($customer) ? app(\App\Services\PersonOpeningBalance::class)->read($customer) : [];
@endphp
<section class="border rounded p-3 mx-3 mb-4">
    <h4>Opening Balances</h4>
    <p>Enter balances brought forward, not new payments. Dr: person owes us. Cr: we owe the person. Worker metal is net grams; customer metal is fine grams.</p>
    <div class="row">
        <div class="col-md-4 mb-3"><label for="opening-date">Opening Date</label><input id="opening-date" type="date" name="opening[date]" class="form-control" required value="{{ old('opening.date', $opening['date'] ?? now()->toDateString()) }}"></div>
    </div>
    <div class="row">
        @foreach(['cash'=>'Cash Amount (Rs)', 'gold'=>'Gold (g)', 'silver'=>'Silver (g)', 'other'=>'Other Metal (g)'] as $asset=>$label)
        <div class="col-md-3 mb-3">
            <label for="opening-{{ $asset }}">{{ $label }}</label>
            <input id="opening-{{ $asset }}" type="number" min="0" max="999999999" step="{{ $asset === 'cash' ? '0.01' : '0.001' }}" name="opening[{{ $asset }}]" class="form-control mb-2" required value="{{ old('opening.'.$asset, $opening[$asset] ?? 0) }}">
            <select name="opening[{{ $asset }}_side]" class="form-select" aria-label="{{ $label }} debit or credit">
                <option value="dr" @selected(old('opening.'.$asset.'_side', $opening[$asset.'_side'] ?? 'dr') === 'dr')>Dr — Person owes us</option>
                <option value="cr" @selected(old('opening.'.$asset.'_side', $opening[$asset.'_side'] ?? 'dr') === 'cr')>Cr — We owe person</option>
            </select>
        </div>
        @endforeach
    </div>
</section>
