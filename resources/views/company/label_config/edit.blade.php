@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">

        <div class="card-header">
            <h4 class="card-title">Edit Label Config</h4>
        </div>


        <div class="card-body">

            <form method="POST"
                action="{{ route('company.label_config.update', [$company->slug, Crypt::encryptString($labelConfig->id)]) }}">

                @csrf
                @method('PUT')


                <div class="row">

                    {{-- ITEM --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Item *</label>

                            <select name="item_id" class="form-select" required>

                                <option value="">Select Item</option>

                                @foreach($items as $item)

                                <option value="{{ $item->id }}"
                                    {{ old('item_id', $labelConfig->item_id) == $item->id ? 'selected' : '' }}>
                                    {{ $item->item_name }}
                                </option>

                                @endforeach

                            </select>

                        </div>
                    </div>


                    {{-- PREFIX --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>Prefix</label>

                            <input type="text"
                                name="prefix"
                                value="{{ old('prefix', $labelConfig->prefix) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- NUMERIC LENGTH --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>Numeric Length</label>

                            <input type="number"
                                name="numeric_length"
                                value="{{ old('numeric_length', $labelConfig->numeric_length) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- LAST NO --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>Last No</label>

                            <input type="number"
                                name="last_no"
                                value="{{ old('last_no', $labelConfig->last_no) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- MIN NO --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>Min No</label>

                            <input type="number"
                                name="min_no"
                                value="{{ old('min_no', $labelConfig->min_no) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- MAX NO --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>Max No</label>

                            <input type="number"
                                name="max_no"
                                value="{{ old('max_no', $labelConfig->max_no) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- FROM DATE --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>From Date</label>

                            <input type="date"
                                name="from_date"
                                value="{{ old('from_date', $labelConfig->from_date) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- TO DATE --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>To Date</label>

                            <input type="date"
                                name="to_date"
                                value="{{ old('to_date', $labelConfig->to_date) }}"
                                class="form-control">

                        </div>
                    </div>


                    {{-- REUSE --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>

                                <input type="checkbox"
                                    name="reuse"
                                    value="1"
                                    {{ old('reuse', $labelConfig->reuse) ? 'checked' : '' }}>

                                Reuse

                            </label>

                        </div>
                    </div>


                    {{-- RANDOM --}}
                    <div class="col-md-6">
                        <div class="form-group">

                            <label>

                                <input type="checkbox"
                                    name="random"
                                    value="1"
                                    {{ old('random', $labelConfig->random) ? 'checked' : '' }}>

                                Random

                            </label>

                        </div>
                    </div>


                </div>

        </div>


        <div class="card-footer text-end">

            <a href="{{ route('company.label_config.index', $company->slug) }}"
                class="btn btn-info">
                Back
            </a>

            <button type="submit"
                class="btn btn-primary">
                Update 
            </button>

        </div>

        </form>

    </div>

</div>

@endsection
