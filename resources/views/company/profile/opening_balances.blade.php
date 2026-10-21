<div class="card mb-4" id="opening-balances">
    <div class="card-header"><h3 class="mb-0">Company opening balances</h3></div>
    <div class="card-body">
        <p>Enter your starting cash, bank and metal balances. Metal quantities are fine grams. These profile balances are not yet included in stock or transaction reports.</p>
        @if(session('opening_success'))<div class="alert alert-success">{{ session('opening_success') }}</div>@endif
        <form method="POST" action="{{ route('company.profile.opening-balances', $company->slug) }}">
            @csrf @method('PUT')
            <div class="row">
                <div class="col-md-4 mb-3"><label for="opening_balance_date">Opening date</label><input id="opening_balance_date" name="opening_balance_date" type="date" class="form-control" value="{{ old('opening_balance_date', $company->opening_balance_date) }}" required>@error('opening_balance_date')<div class="text-danger">{{ $message }}</div>@enderror</div>
                @foreach(['opening_cash'=>'Cash in hand (INR)', 'opening_bank'=>'Bank balance (INR)', 'opening_gold_fine'=>'Gold opening balance (fine g)', 'opening_silver_fine'=>'Silver opening balance (fine g)'] as $key=>$label)
                <div class="col-md-4 mb-3"><label for="{{ $key }}">{{ $label }}</label><input id="{{ $key }}" name="{{ $key }}" type="number" min="0" step="{{ str_contains($key,'fine') ? '0.001' : '0.01' }}" class="form-control" value="{{ old($key, $company->$key ?? 0) }}" required>@error($key)<div class="text-danger">{{ $message }}</div>@enderror</div>
                @endforeach
            </div>
            <button class="btn btn-primary">Save opening balances</button>
        </form>
    </div>
</div>
