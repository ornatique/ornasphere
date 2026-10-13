@if(\Illuminate\Support\Facades\Schema::hasColumn('items', 'try_on_category'))
<div class="form-group mb-3">
    <label for="try_on_category">Try-On Category (Stock Gallery)</label>
    <select name="try_on_category" id="try_on_category" class="form-select">
        @foreach(['none' => 'None / Gallery only', 'earrings' => 'Earrings', 'necklace' => 'Necklace', 'ring' => 'Ring', 'bracelet' => 'Bracelet / Bangle'] as $value => $label)
            <option value="{{ $value }}" @selected(old('try_on_category', $item->try_on_category ?? 'none') === $value)>{{ $label }}</option>
        @endforeach
    </select>
    <small class="text-muted">Used only for camera preview placement. Stock type and weight calculations are unchanged.</small>
</div>

@endif
