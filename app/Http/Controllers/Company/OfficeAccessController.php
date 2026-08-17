<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyOfficeAccessSetting;
use Illuminate\Http\Request;

class OfficeAccessController extends Controller
{
    public function edit(Request $request, string $slug)
    {
        $this->authorizeCompanyAdmin($request);

        $company = Company::whereSlug($slug)->firstOrFail();
        $setting = $this->setting($company);

        return view('company.office_access.edit', compact('company', 'setting'));
    }

    public function update(Request $request, string $slug)
    {
        $this->authorizeCompanyAdmin($request);

        $company = Company::whereSlug($slug)->firstOrFail();

        $validated = $request->validate([
            'geo_enabled' => 'nullable|boolean',
            'device_approval_enabled' => 'nullable|boolean',
            'office_latitude' => 'nullable|numeric|between:-90,90',
            'office_longitude' => 'nullable|numeric|between:-180,180',
            'allowed_radius_meters' => 'required|integer|min:10|max:10000',
            'emergency_override_enabled' => 'nullable|boolean',
            'emergency_override_until' => 'nullable|date',
            'emergency_override_reason' => 'nullable|string|max:1000',
        ]);

        $setting = $this->setting($company);
        $emergencyEnabled = $request->boolean('emergency_override_enabled');

        $setting->forceFill([
            'geo_enabled' => $request->boolean('geo_enabled'),
            'device_approval_enabled' => $request->boolean('device_approval_enabled'),
            'office_latitude' => $validated['office_latitude'] ?? null,
            'office_longitude' => $validated['office_longitude'] ?? null,
            'allowed_radius_meters' => $validated['allowed_radius_meters'],
            'emergency_override_enabled' => $emergencyEnabled,
            'emergency_override_until' => $emergencyEnabled ? ($validated['emergency_override_until'] ?? null) : null,
            'emergency_override_reason' => $emergencyEnabled ? ($validated['emergency_override_reason'] ?? null) : null,
            'emergency_override_by' => $emergencyEnabled ? $request->user()->id : null,
        ])->save();

        return redirect()
            ->route('company.office-access.edit', $company->slug)
            ->with('success', 'Office access settings updated successfully.');
    }

    private function setting(Company $company): CompanyOfficeAccessSetting
    {
        return CompanyOfficeAccessSetting::firstOrCreate(
            ['company_id' => $company->id],
            [
                'geo_enabled' => false,
                'device_approval_enabled' => true,
                'allowed_radius_meters' => 100,
            ]
        );
    }

    private function authorizeCompanyAdmin(Request $request): void
    {
        $user = $request->user();

        if (
            !$user
            || (
                !$user->hasRole('company_admin')
                && !$user->can('office-access-view')
                && !$user->can('office-access-edit')
                && !$user->can('office-access-manage')
            )
        ) {
            abort(403, 'Only company admin can manage office access settings.');
        }
    }
}
