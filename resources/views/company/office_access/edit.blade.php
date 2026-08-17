@extends('company_layout.admin')

@section('content')
<div class="content-wrapper">
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="card-title mb-0">Office Access Settings</h4>
            <a href="{{ route('company.users.index', $company->slug) }}" class="btn btn-info">Back</a>
        </div>

        <form method="POST" action="{{ route('company.office-access.update', $company->slug) }}">
            @csrf

            <div class="card-body">
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <p class="card-description">Worker App Security</p>

                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Office Radius Check</label>
                            <div class="col-sm-8">
                                <select name="geo_enabled" class="form-control">
                                    <option value="1" {{ old('geo_enabled', $setting->geo_enabled ? '1' : '0') == '1' ? 'selected' : '' }}>Enable</option>
                                    <option value="0" {{ old('geo_enabled', $setting->geo_enabled ? '1' : '0') == '0' ? 'selected' : '' }}>Disable</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="form-group row">
                            <label class="col-sm-4 col-form-label">Approved Device Check</label>
                            <div class="col-sm-8">
                                <select name="device_approval_enabled" class="form-control">
                                    <option value="1" {{ old('device_approval_enabled', $setting->device_approval_enabled ? '1' : '0') == '1' ? 'selected' : '' }}>Enable</option>
                                    <option value="0" {{ old('device_approval_enabled', $setting->device_approval_enabled ? '1' : '0') == '0' ? 'selected' : '' }}>Disable</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Office Latitude</label>
                            <input type="number"
                                   step="0.0000001"
                                   name="office_latitude"
                                   value="{{ old('office_latitude', $setting->office_latitude) }}"
                                   class="form-control"
                                   placeholder="21.1702400">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Office Longitude</label>
                            <input type="number"
                                   step="0.0000001"
                                   name="office_longitude"
                                   value="{{ old('office_longitude', $setting->office_longitude) }}"
                                   class="form-control"
                                   placeholder="72.8310610">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Allowed Radius Meter</label>
                            <input type="number"
                                   min="10"
                                   max="10000"
                                   name="allowed_radius_meters"
                                   value="{{ old('allowed_radius_meters', $setting->allowed_radius_meters ?: 100) }}"
                                   class="form-control"
                                   required>
                        </div>
                    </div>
                </div>

                <p class="card-description mt-4">Emergency Override</p>

                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Emergency Override</label>
                            <select name="emergency_override_enabled" class="form-control">
                                <option value="0" {{ old('emergency_override_enabled', $setting->emergency_override_enabled ? '1' : '0') == '0' ? 'selected' : '' }}>Disable</option>
                                <option value="1" {{ old('emergency_override_enabled', $setting->emergency_override_enabled ? '1' : '0') == '1' ? 'selected' : '' }}>Enable</option>
                            </select>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Override Until</label>
                            <input type="datetime-local"
                                   name="emergency_override_until"
                                   value="{{ old('emergency_override_until', optional($setting->emergency_override_until)->format('Y-m-d\TH:i')) }}"
                                   class="form-control">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-group">
                            <label>Reason</label>
                            <input type="text"
                                   name="emergency_override_reason"
                                   value="{{ old('emergency_override_reason', $setting->emergency_override_reason) }}"
                                   class="form-control"
                                   placeholder="Emergency reason">
                        </div>
                    </div>
                </div>

                <div class="alert alert-info mb-0">
                    Admin users can use the system anywhere. These rules apply to worker mobile app APIs only.
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="submit" class="btn btn-primary">Save Settings</button>
            </div>
        </form>
    </div>
</div>
@endsection
