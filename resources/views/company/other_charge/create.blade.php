@extends('company_layout.admin')

@section('content')

<div class="content-wrapper">

    <div class="card">
        <div class="card-header d-flex justify-content-between">
            <h4 class="card-title">{{ isset($data) ? 'Edit Other Charge' : 'Create Other Charge' }}</h4>
        </div>
        <div class="card-body">

            <form method="POST"
                action="{{ isset($data)
                    ? route('company.other-charge.update',[$company->slug,encrypt($data->id)])
                    : route('company.other-charge.store',$company->slug) }}">

                @csrf

                <div class="row">

                    {{-- Other Charge --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Other Charge *</label>
                            <input type="text"
                                name="other_charge"
                                class="form-control"
                                required
                                value="{{ $data->other_charge ?? '' }}">
                        </div>
                    </div>

                    {{-- Code --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Code</label>
                            <input type="text"
                                name="code"
                                class="form-control"
                                value="{{ $data->code ?? '' }}">
                        </div>
                    </div>

                    {{-- Default Amount --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Default Amount</label>
                            <input type="number"
                                step="0.01"
                                name="default_amount"
                                class="form-control"
                                value="{{ $data->default_amount ?? '' }}">
                        </div>
                    </div>


                    {{-- Default Weight --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Default Weight</label>
                            <input type="number"
                                step="0.001"
                                name="default_weight"
                                class="form-control"
                                value="{{ $data->default_weight ?? '' }}">
                        </div>
                    </div>


                    {{-- Quantity Pcs --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Quantity Pcs</label>
                            <input type="number"
                                name="quantity_pcs"
                                class="form-control"
                                value="{{ $data->quantity_pcs ?? '' }}">
                        </div>
                    </div>


                    {{-- Weight Formula --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Weight Formula</label>
                            <select name="weight_formula"
                                class="form-select">

                                <option value="">Select Formula</option>

                                <option value="per_quantity"
                                    {{ isset($data) && $data->weight_formula=='per_quantity' ? 'selected':'' }}>
                                    Per Quantity
                                </option>

                                <option value="flat"
                                    {{ isset($data) && $data->weight_formula=='flat' ? 'selected':'' }}>
                                    Flat
                                </option>

                            </select>
                        </div>
                    </div>


                    {{-- Weight Percent --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Weight %</label>
                            <input type="number"
                                step="0.01"
                                name="weight_percent"
                                class="form-control"
                                value="{{ $data->weight_percent ?? '' }}">
                        </div>
                    </div>


                    {{-- Sale Weight Percent --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Sale Weight %</label>
                            <input type="number"
                                step="0.01"
                                name="sale_weight_percent"
                                class="form-control"
                                value="{{ $data->sale_weight_percent ?? '' }}">
                        </div>
                    </div>


                    {{-- Purchase Weight Percent --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Purchase Weight %</label>
                            <input type="number"
                                step="0.01"
                                name="purchase_weight_percent"
                                class="form-control"
                                value="{{ $data->purchase_weight_percent ?? '' }}">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Other Amount Formula</label>

                            <select name="other_amt_formula"
                                class="form-select">

                                <option value="">Select Value</option>
                                <option value="per_weight"
                                    {{ isset($data) && $data->other_amt_formula=='per_weight' ? 'selected':'' }}>
                                    Per Weight
                                </option>
                                <option value="per_quantity"
                                    {{ isset($data) && $data->other_amt_formula=='per_quantity' ? 'selected':'' }}>
                                    Per Quantity
                                </option>

                                <option value="carat"
                                    {{ isset($data) && $data->other_amt_formula=='carat' ? 'selected':'' }}>
                                    Carat
                                </option>

                                <option value="flat"
                                    {{ isset($data) && $data->other_amt_formula=='flat' ? 'selected':'' }}>
                                    Flat
                                </option>
                            </select>
                        </div>
                    </div>

                    {{-- Sequence --}}
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Sequence No</label>
                            <input type="number"
                                name="sequence_no"
                                class="form-control"
                                value="{{ $data->sequence_no ?? '' }}">
                        </div>
                    </div>

                    <div class="col-md-3 mt-5">

                        <label class="mr-3">

                            <input type="checkbox"
                                name="is_default"
                                value="1"
                                {{ isset($data)&&$data->is_default?'checked':'' }}>

                            Default

                        </label>


                        <label class="mr-3">

                            <input type="checkbox"
                                name="is_selected"
                                value="1"
                                {{ isset($data)&&$data->is_selected?'checked':'' }}>

                            Selected

                        </label>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Other Charge OL</label>
                            <input type="number"
                                step="0.01"
                                name="other_charge_ol"
                                class="form-control"
                                value="{{ $data->other_charge_ol ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Purity</label>
                            <input type="number"
                                step="0.01"
                                name="purity"
                                class="form-control"
                                value="{{ $data->purity ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Required Purity</label>
                            <input type="number"
                                step="0.01"
                                name="required_purity"
                                class="form-control"
                                value="{{ $data->required_purity ?? '' }}">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-group">
                            <label>Merge Other Charge</label>
                            <select name="merge_other_charge"
                                class="form-select">

                                <option value="">Select Value</option>

                                <option value="per_quantity"
                                    {{ isset($data) && $data->merge_other_charge=='per_quantity' ? 'selected':'' }}>
                                    Per Quantity
                                </option>

                                <option value="flat"
                                    {{ isset($data) && $data->merge_other_charge=='flat' ? 'selected':'' }}>
                                    Flat
                                </option>

                            </select>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="form-group">
                            <label>WT Operation</label>
                            <select name="wt_operation"
                                class="form-select">

                                <option value="">Select value</option>

                                <option value="add"
                                    {{ isset($data) && $data->wt_operation=='add' ? 'selected':'' }}>
                                    Add
                                </option>

                                <option value="less"
                                    {{ isset($data) && $data->wt_operation=='less' ? 'selected':'' }}>
                                    Less
                                </option>

                            </select>
                        </div>
                    </div>
                    {{-- Item --}}
                    <div class="col-md-6">
                        <div class="form-group">
                            <label>Item</label>

                            <select name="item_id"
                                class="form-select">

                                <option value="">Select Item</option>

                                @foreach($items as $item)

                                <option value="{{ $item->id }}"
                                    {{ isset($data) && $data->item_id==$item->id ? 'selected':'' }}>

                                    {{ $item->item_name }}

                                </option>

                                @endforeach

                            </select>
                        </div>
                    </div>


                    {{-- Checkboxes --}}
                    <div class="col-md-12">

                        <div class="form-group">

                             <label class="mr-3">

                                <input type="checkbox"
                                    name="carat_weight_auto_conversion"
                                    value="1"
                                    {{ isset($data)&&$data->carat_weight_auto_conversion?'checked':'' }}>

                                Carat Weight auto Conversion

                            </label>



                            <label class="mr-3">

                                <input type="checkbox"
                                    name="diamond"
                                    value="1"
                                    {{ isset($data)&&$data->diamond?'checked':'' }}>

                                Diamond

                            </label>


                            <label class="mr-3">

                                <input type="checkbox"
                                    name="stone"
                                    value="1"
                                    {{ isset($data)&&$data->stone?'checked':'' }}>

                                Stone

                            </label>


                            <label class="mr-3">

                                <input type="checkbox"
                                    name="stock_effect"
                                    value="1"
                                    {{ isset($data)&&$data->stock_effect?'checked':'' }}>

                                Stock Effect

                            </label>

                            <label class="mr-3">

                                <input type="checkbox"
                                    name="party_account_effect"
                                    value="1"
                                    {{ isset($data)&&$data->party_account_effect?'checked':'' }}>

                                Party Account Effect

                            </label>

                        </div>

                    </div>


                    {{-- Remarks --}}
                    <div class="col-md-12">

                        <div class="form-group">

                            <label>Remarks</label>

                            <textarea name="remarks"
                                rows="3"
                                class="form-control">{{ $data->remarks ?? '' }}</textarea>

                        </div>

                    </div>


                </div>
        </div>
        <div class="card-footer text-end">
            {{-- Buttons --}}


            <button type="submit"
                class="btn btn-success">

                {{ isset($data) ? 'Update' : 'Save' }}

            </button>


            <a href="{{ route('company.other-charge.index',$company->slug) }}"
                class="btn btn-secondary">

                Cancel

            </a>


        </div>

        </form>

    </div>

</div>

@endsection