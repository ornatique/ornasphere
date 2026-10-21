@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Create Person</h3><a class="btn btn-info" href="{{ route('company.gst-search.create', $company->slug) }}">Search GSTIN</a>
                </div>

                <div class="card-body">
                    @if(!empty($gstPrefill))
                    <div class="alert alert-info">Customer details loaded from GST Search. Review the prefilled fields below before saving.</div>
                    @endif
                    <form method="POST" action="{{ route('company.customers.store', $company->slug) }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Name</label>
                                    <div class="col-sm-9"><input type="text" name="name" value="{{ old('name', $gstPrefill['name'] ?? '') }}" class="form-control" required></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email</label>
                                    <div class="col-sm-9">
                                        <input type="email" name="email" value="{{ old('email') }}" class="form-control @error('email') is-invalid @enderror">
                                        @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Category Person</label>
                                    <div class="col-sm-9">
                                        <select name="category_person_id" class="form-control searchable-person-select @error('category_person_id') is-invalid @enderror">
                                            <option value="">Select Category Person</option>
                                            @foreach($categoryPeople as $categoryPerson)
                                                <option value="{{ $categoryPerson->id }}" {{ (string) old('category_person_id', $gstPrefill['category_person_id'] ?? '') === (string) $categoryPerson->id ? 'selected' : '' }}>
                                                    {{ $categoryPerson->category_name }}
                                                </option>
                                            @endforeach
                                        </select>
                                        @error('category_person_id')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Mobile No</label>
                                    <div class="col-sm-9"><input type="text" name="mobile_no" value="{{ old('mobile_no') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">City</label>
                                    <div class="col-sm-9"><input type="text" id="city" name="city" value="{{ old('city', $gstPrefill['city'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Area</label>
                                    <div class="col-sm-9"><input type="text" id="area" name="area" value="{{ old('area', $gstPrefill['area'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Landmark</label>
                                    <div class="col-sm-9"><input type="text" id="landmark" name="landmark" value="{{ old('landmark', $gstPrefill['landmark'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Pincode</label>
                                    <div class="col-sm-9"><input type="text" id="pincode" name="pincode" value="{{ old('pincode', $gstPrefill['pincode'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address</label>
                                    <div class="col-sm-9"><textarea id="address" name="address" class="form-control">{{ old('address', $gstPrefill['address'] ?? '') }}</textarea></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 1 Name</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person1_name" value="{{ old('contact_person1_name') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 1 Phone</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person1_phone" value="{{ old('contact_person1_phone') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 2 Name</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person2_name" value="{{ old('contact_person2_name') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 2 Phone</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person2_phone" value="{{ old('contact_person2_phone') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">GST No</label>
                                    <div class="col-sm-9"><input type="text" name="gst_no" value="{{ old('gst_no', $gstPrefill['gst_no'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">PAN No</label>
                                    <div class="col-sm-9"><input type="text" name="pan_no" value="{{ old('pan_no', $gstPrefill['pan_no'] ?? '') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Aadhaar No</label>
                                    <div class="col-sm-9"><input type="text" name="aadhaar_no" value="{{ old('aadhaar_no') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Reference</label>
                                    <div class="col-sm-9"><input type="text" name="reference" value="{{ old('reference') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Birth Date</label>
                                    <div class="col-sm-9"><input type="date" name="birth_date" value="{{ old('birth_date') }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Anniversary Date</label>
                                    <div class="col-sm-9"><input type="date" name="anniversary_date" value="{{ old('anniversary_date') }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Remarks</label>
                                    <div class="col-sm-9"><textarea name="remarks" class="form-control" rows="4">{{ old('remarks', $gstPrefill['remarks'] ?? '') }}</textarea></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Status</label>
                                    <div class="col-sm-9">
                                        <select name="is_active" class="form-control searchable-person-select">
                                            <option value="1" {{ old('is_active', '1') == '1' ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ old('is_active') == '0' ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>

                @include('company.customers.partials.opening_balance')
                <div class="card-footer text-end">
                    <a href="{{ route('company.customers.index', $company->slug) }}" class="btn btn-info">Back</a>
                    <button type="submit" class="btn btn-primary">Save</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@include('company.customers.partials.address_autofill')
@include('company.customers.partials.identity_input_rules')

@push("scripts")
<script>
    document.addEventListener('DOMContentLoaded', function () {
        if (window.jQuery && $.fn.select2) {
            $('.searchable-person-select').select2({
                theme: 'bootstrap4',
                width: '100%',
                minimumResultsForSearch: 0
            });
        }
    });
</script>
@endpush
