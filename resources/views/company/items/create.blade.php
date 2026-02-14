@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Create Item</h3>
        </div>

        <div class="card-body">

            <form method="POST" action="{{ route('company.items.store', $company->slug) }}">
                @csrf

                {{-- GLOBAL ERROR --}}
                @if ($errors->any())
                <div class="alert alert-danger">
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                @endif

                {{-- ================= BASIC DETAILS ================= --}}
                <h5 class="mb-3">Item Details</h5>

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Item Name *</label>
                            <input type="text" name="item_name"
                                value="{{ old('item_name') }}"
                                class="form-control @error('item_name') is-invalid @enderror">
                            @error('item_name')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Item Code *</label>
                            <input type="text" name="item_code"
                                value="{{ old('item_code') }}"
                                class="form-control @error('item_code') is-invalid @enderror">
                            @error('item_code')<small class="text-danger">{{ $message }}</small>@enderror
                        </div>
                    </div>

                </div>


                {{-- ================= METAL DETAILS ================= --}}
                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Metal</label>
                            <select name="metal" class="form-select">
                                <option value="">Select Metal</option>
                                <option value="Gold" {{ old('metal')=='Gold'?'selected':'' }}>Gold</option>
                                <option value="Silver" {{ old('metal')=='Silver'?'selected':'' }}>Silver</option>
                                <option value="Platinum" {{ old('metal')=='Platinum'?'selected':'' }}>Platinum</option>
                                <option value="Others" {{ old('metal')=='Others'?'selected':'' }}>Others</option>
                            </select>
                        </div>
                    </div>

                    

                </div>

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Outward Carat</label>
                            <input type="number" name="outward_carat"
                                value="{{ old('outward_carat') }}"
                                class="form-control">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Inward Carat</label>
                            <input type="number" name="inward_carat"
                                value="{{ old('inward_carat') }}"
                                class="form-control">
                        </div>
                    </div>

                </div>

                {{-- ================= PURITY ================= --}}
                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Outward Purity</label>
                            <input type="number" name="outward_purity"
                                value="{{ old('outward_purity') }}"
                                class="form-control">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Inward Purity</label>
                            <input type="number" name="inward_purity"
                                value="{{ old('inward_purity') }}"
                                class="form-control">
                        </div>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Metal Formula</label>
                            <select name="metal_formula" class="form-select">
                                <option value="">Select Value</option>
                                <option value="per_netweight" {{ old('metal_formula')=='per_netweight'?'selected':'' }}>Per Netweight</option>
                                <option value="per_fineweight" {{ old('metal_formula')=='per_fineweight'?'selected':'' }}>Per Fineweight</option>
                                <option value="per_grossweight" {{ old('metal_formula')=='per_grossweight'?'selected':'' }}>Per Grossweight</option>
                                <option value="per_quantity" {{ old('metal_formula')=='per_quantity'?'selected':'' }}>Per Quantity</option>
                                <option value="flat" {{ old('metal_formula')=='flat'?'selected':'' }}>Flat</option>

                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Labour</label>
                            <select name="labour_type" class="form-select">
                                <option value="">Select Value</option>
                                <option value="per_netweight" {{ old('labour_type')=='per_netweight'?'selected':'' }}>Per Netweight</option>
                                <option value="per_fineweight" {{ old('labour_type')=='per_fineweight'?'selected':'' }}>Per Fineweight</option>
                                <option value="per_grossweight" {{ old('labour_type')=='per_grossweight'?'selected':'' }}>Per Grossweight</option>
                                <option value="per_quantity" {{ old('labour_type')=='per_quantity'?'selected':'' }}>Per Quantity</option>
                                <option value="flat" {{ old('labour_type')=='flat'?'selected':'' }}>Flat</option>

                            </select>
                        </div>
                    </div>

                </div>

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Labour Unit</label>
                            <select name="labour_unit" class="form-select">
                                <option value="">Select Value</option>
                                <option value="per_gram" {{ old('labour_unit')=='per_gram'?'selected':'' }}>Per Gram</option>
                                <option value="per_10_gram" {{ old('labour_unit')=='per_10_gram'?'selected':'' }}>Per 10 Gram</option>
                                <option value="per_kg" {{ old('labour_unit')=='per_kg'?'selected':'' }}>Per KG</option>
                                <option value="per_quantity" {{ old('labour_unit')=='per_quantity'?'selected':'' }}>Per Quantity</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Jobwork Item Type</label>
                            <select name="jobwork_item_type" class="form-select">
                                <option value="">Select Value</option>
                                <option value="finish" {{ old('jobwork_item_type')=='finish'?'selected':'' }}>Finish</option>
                                <option value="raw" {{ old('jobwork_item_type')=='raw'?'selected':'' }}>Raw</option>

                            </select>
                        </div>
                    </div>

                </div>

                {{-- ================= TAX ================= --}}
                <div class="row mt-3">


                    <div class="col-md-6">
                        <div class="form-group">
                            <label>HSN Code</label>
                            <input type="text" name="hsn"
                                value="{{ old('hsn') }}"
                                class="form-control">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Export HSN</label>
                            <input type="text" name="export_hsn"
                                value="{{ old('export_hsn') }}"
                                class="form-control">
                        </div>
                    </div>

                </div>


                {{-- ================= CHECKBOX ================= --}}
                <div class="row mt-3">

                    <div class="col-md-3">
                        <div class="form-check">
                            <input type="checkbox" name="auto_load_purity" value="1"
                                {{ old('auto_load_purity')?'checked':'' }}>
                            <label>Auto Load Purity From Prod. in jobWork</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check">
                            <input type="checkbox" name="auto_create_label_purchase" value="1"
                                {{ old('auto_create_label_purchase')?'checked':'' }}>
                            <label>Auto Create Label IN Purchase</label>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-check">
                            <input type="checkbox" name="auto_create_label_config" value="1"
                                {{ old('auto_create_label_config')?'checked':'' }}>
                            <label>Auto Create Label Config</label>
                        </div>
                    </div>

                </div>


                {{-- ================= REMARKS ================= --}}

                <div class="row">

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>NUmeric Length</label>
                            <input type="number" name="numeric_length"
                                value="{{ old('numeric_length') }}"
                                class="form-control">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Item Group</label>
                            <select name="item_group" class="form-select">
                                <option value="">Select Value</option>
                                <option value="finish" {{ old('item_group')=='finish'?'selected':'' }}>Finish</option>
                                <option value="raw" {{ old('item_group')=='raw'?'selected':'' }}>Raw</option>

                            </select>
                        </div>
                    </div>

                </div>
                <div class="row mt-3">
                    <div class="col-md-12">
                        <div class="form-group">
                            <label>Remarks</label>
                            <textarea name="remarks" class="form-control">{{ old('remarks') }}</textarea>
                        </div>
                    </div>
                </div>


        </div>

        <div class="card-footer text-end">
            <a href="{{ route('company.items.index', $company->slug) }}"
                class="btn btn-info">
                Back
            </a>
            <button type="submit" class="btn btn-primary">Save Item</button>
        </div>

        </form>

    </div>
</div>
@endsection