<div class="card-body border-top">
    <h4>Allowed Modules</h4>
    <p class="text-muted">Only selected modules appear for this company and its branches. Dashboard, profile, security and logout remain available.</p>
    <input type="hidden" name="modules_present" value="1">
    @php
        $selectedModules = old('modules_present')
            ? old('allowed_modules', [])
            : (isset($company) ? \App\Services\CompanyModules::allowed($company) : []);
    @endphp
    <div class="row">
        @foreach(config('company_modules') as $moduleKey => $moduleDefinition)
            <div class="col-md-4 col-sm-6 mb-3">
                <label class="d-flex align-items-center" style="gap:10px">
                    <input type="checkbox" name="allowed_modules[]" value="{{ $moduleKey }}" @checked(in_array($moduleKey, $selectedModules, true))>
                    {{ $moduleDefinition['label'] }}
                </label>
            </div>
        @endforeach
    </div>
    @error('allowed_modules') <div class="text-danger">{{ $message }}</div> @enderror
    @error('allowed_modules.*') <div class="text-danger">{{ $message }}</div> @enderror
</div>
