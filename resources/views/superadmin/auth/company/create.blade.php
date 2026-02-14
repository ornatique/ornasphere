@extends('layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">

                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Add Company Details</h3>
                </div>

                <div class="card-body"> 

                    <form method="POST"
                          action="{{ route('superadmin.companies.store') }}">
                        @csrf

                        <p class="card-description">Personal Info</p>

                        {{-- Company Name & Email --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Company Name</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="name"
                                               value="{{ old('name') }}"
                                               class="form-control @error('name') is-invalid @enderror">
                                        @error('name')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email ID</label>
                                    <div class="col-sm-9">
                                        <input type="email"
                                               name="email"
                                               value="{{ old('email') }}"
                                               class="form-control @error('email') is-invalid @enderror">
                                        @error('email')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Max Users --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">User Allow</label>
                                    <div class="col-sm-9">
                                        <input type="number"
                                               name="max_users"
                                               value="{{ old('max_users') }}"
                                               class="form-control @error('max_users') is-invalid @enderror">
                                        @error('max_users')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        <p class="card-description">Address</p>

                        {{-- Address 1 & State --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address 1</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="address_1"
                                               value="{{ old('address_1') }}"
                                               class="form-control @error('address_1') is-invalid @enderror">
                                        @error('address_1')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">State</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="state"
                                               value="{{ old('state') }}"
                                               class="form-control @error('state') is-invalid @enderror">
                                        @error('state')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- Address 2 & Postcode --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address 2</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="address_2"
                                               value="{{ old('address_2') }}"
                                               class="form-control">
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Postcode</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="postcode"
                                               value="{{ old('postcode') }}"
                                               class="form-control @error('postcode') is-invalid @enderror">
                                        @error('postcode')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- City & Country --}}
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">City</label>
                                    <div class="col-sm-9">
                                        <input type="text"
                                               name="city"
                                               value="{{ old('city') }}"
                                               class="form-control @error('city') is-invalid @enderror">
                                        @error('city')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Country</label>
                                    <div class="col-sm-9">
                                        <select name="country"
                                                class="form-select @error('country') is-invalid @enderror">
                                            <option value="">Select Country</option>
                                            <option value="America" {{ old('country')=='America'?'selected':'' }}>America</option>
                                            <option value="India" {{ old('country')=='India'?'selected':'' }}>India</option>
                                            <option value="Italy" {{ old('country')=='Italy'?'selected':'' }}>Italy</option>
                                            <option value="Russia" {{ old('country')=='Russia'?'selected':'' }}>Russia</option>
                                            <option value="Britain" {{ old('country')=='Britain'?'selected':'' }}>Britain</option>
                                        </select>
                                        @error('country')
                                            <div class="invalid-feedback">{{ $message }}</div>
                                        @enderror
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ACTION BUTTONS --}}
                        <div class="card-footer text-end">
                            <a href="{{ route('superadmin.companies.index') }}" class="btn btn-info">
                                Back
                            </a>
                            <button type="submit" class="btn btn-primary">
                                Save
                            </button>
                        </div>

                    </form>

                </div>
            </div>
        </div>
    </div>
</div>
@endsection
