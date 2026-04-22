@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="row">
        <div class="col-12 grid-margin">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="mb-0">Edit Job Worker</h3>
                </div>

                <div class="card-body">
                    <form method="POST" action="{{ route('company.job-workers.update', [$company->slug, Crypt::encryptString($jobWorker->id)]) }}">
                        @csrf
                        @method('PUT')

                        @if ($errors->any())
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                        @endif

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Name</label>
                                    <div class="col-sm-9"><input type="text" name="name" value="{{ old('name', $jobWorker->name) }}" class="form-control" required></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Email</label>
                                    <div class="col-sm-9"><input type="email" name="email" value="{{ old('email', $jobWorker->email) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Mobile No</label>
                                    <div class="col-sm-9"><input type="text" name="mobile_no" value="{{ old('mobile_no', $jobWorker->mobile_no) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">City</label>
                                    <div class="col-sm-9"><input type="text" name="city" value="{{ old('city', $jobWorker->city) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Area</label>
                                    <div class="col-sm-9"><input type="text" name="area" value="{{ old('area', $jobWorker->area) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Landmark</label>
                                    <div class="col-sm-9"><input type="text" name="landmark" value="{{ old('landmark', $jobWorker->landmark) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Pincode</label>
                                    <div class="col-sm-9"><input type="text" name="pincode" value="{{ old('pincode', $jobWorker->pincode) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Address</label>
                                    <div class="col-sm-9"><textarea name="address" class="form-control">{{ old('address', $jobWorker->address) }}</textarea></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 1 Name</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person1_name" value="{{ old('contact_person1_name', $jobWorker->contact_person1_name) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 1 Phone</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person1_phone" value="{{ old('contact_person1_phone', $jobWorker->contact_person1_phone) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 2 Name</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person2_name" value="{{ old('contact_person2_name', $jobWorker->contact_person2_name) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Contact 2 Phone</label>
                                    <div class="col-sm-9"><input type="text" name="contact_person2_phone" value="{{ old('contact_person2_phone', $jobWorker->contact_person2_phone) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">GST No</label>
                                    <div class="col-sm-9"><input type="text" name="gst_no" value="{{ old('gst_no', $jobWorker->gst_no) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">PAN No</label>
                                    <div class="col-sm-9"><input type="text" name="pan_no" value="{{ old('pan_no', $jobWorker->pan_no) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Aadhaar No</label>
                                    <div class="col-sm-9"><input type="text" name="aadhaar_no" value="{{ old('aadhaar_no', $jobWorker->aadhaar_no) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Reference</label>
                                    <div class="col-sm-9"><input type="text" name="reference" value="{{ old('reference', $jobWorker->reference) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Birth Date</label>
                                    <div class="col-sm-9"><input type="date" name="birth_date" value="{{ old('birth_date', $jobWorker->birth_date) }}" class="form-control"></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Anniversary Date</label>
                                    <div class="col-sm-9"><input type="date" name="anniversary_date" value="{{ old('anniversary_date', $jobWorker->anniversary_date) }}" class="form-control"></div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Remarks</label>
                                    <div class="col-sm-9"><textarea name="remarks" class="form-control">{{ old('remarks', $jobWorker->remarks) }}</textarea></div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group row">
                                    <label class="col-sm-3 col-form-label">Status</label>
                                    <div class="col-sm-9">
                                        <select name="is_active" class="form-control">
                                            <option value="1" {{ (int) old('is_active', $jobWorker->is_active) === 1 ? 'selected' : '' }}>Active</option>
                                            <option value="0" {{ (int) old('is_active', $jobWorker->is_active) === 0 ? 'selected' : '' }}>Inactive</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                </div>

                <div class="card-footer text-end">
                    <a href="{{ route('company.job-workers.index', $company->slug) }}" class="btn btn-info">Back</a>
                    <button type="submit" class="btn btn-primary">Update</button>
                </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

