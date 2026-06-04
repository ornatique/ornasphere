<?php

namespace App\Http\Controllers\Company;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyAppTheme;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AppThemeController extends Controller
{
    public function index(string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $themes = CompanyAppTheme::where('company_id', $company->id)
            ->latest()
            ->get();

        return view('company.app_themes.index', compact('company', 'themes'));
    }

    public function create(string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $theme = new CompanyAppTheme([
            'name' => 'Default Theme',
            'mode' => 'normal',
            'is_active' => !CompanyAppTheme::where('company_id', $company->id)->exists(),
            'primary_color' => '#000000',
            'secondary_color' => '#FFD700',
            'background_color' => '#FFFFFF',
            'text_color' => '#111111',
            'primary_gradient' => ['#000000', '#333333'],
            'secondary_gradient' => ['#FFD700', '#FFA500'],
            'background_gradient' => ['#FFFFFF', '#F5F5F5'],
            'text_gradient' => ['#111111', '#222222'],
        ]);

        return view('company.app_themes.form', compact('company', 'theme'));
    }

    public function store(Request $request, string $slug)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $data = $this->validatedData($request);

        DB::transaction(function () use ($company, $request, $data) {
            $makeActive = $request->boolean('is_active')
                || !CompanyAppTheme::where('company_id', $company->id)->exists();

            if ($makeActive) {
                CompanyAppTheme::where('company_id', $company->id)->update(['is_active' => false]);
            }

            CompanyAppTheme::create($data + [
                'company_id' => $company->id,
                'is_active' => $makeActive,
                'created_by' => optional(auth()->user())->id,
                'updated_by' => optional(auth()->user())->id,
            ]);
        });

        return redirect()
            ->route('company.app-themes.index', $company->slug)
            ->with('success', 'App theme created successfully.');
    }

    public function edit(string $slug, CompanyAppTheme $theme)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureThemeCompany($theme, $company);

        return view('company.app_themes.form', compact('company', 'theme'));
    }

    public function update(Request $request, string $slug, CompanyAppTheme $theme)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureThemeCompany($theme, $company);
        $data = $this->validatedData($request);

        DB::transaction(function () use ($company, $theme, $request, $data) {
            $makeActive = $request->boolean('is_active')
                || !CompanyAppTheme::where('company_id', $company->id)
                    ->where('id', '!=', $theme->id)
                    ->where('is_active', true)
                    ->exists();

            if ($makeActive) {
                CompanyAppTheme::where('company_id', $company->id)
                    ->where('id', '!=', $theme->id)
                    ->update(['is_active' => false]);
            }

            $theme->update($data + [
                'is_active' => $makeActive,
                'updated_by' => optional(auth()->user())->id,
            ]);
        });

        return redirect()
            ->route('company.app-themes.index', $company->slug)
            ->with('success', 'App theme updated successfully.');
    }

    public function activate(string $slug, CompanyAppTheme $theme)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureThemeCompany($theme, $company);

        DB::transaction(function () use ($company, $theme) {
            CompanyAppTheme::where('company_id', $company->id)->update(['is_active' => false]);
            $theme->update([
                'is_active' => true,
                'updated_by' => optional(auth()->user())->id,
            ]);
        });

        return back()->with('success', 'Theme activated successfully.');
    }

    public function destroy(string $slug, CompanyAppTheme $theme)
    {
        $company = Company::whereSlug($slug)->firstOrFail();
        $this->ensureThemeCompany($theme, $company);
        $wasActive = $theme->is_active;

        $theme->delete();

        if ($wasActive) {
            CompanyAppTheme::where('company_id', $company->id)
                ->latest()
                ->first()?->update(['is_active' => true]);
        }

        return back()->with('success', 'Theme deleted successfully.');
    }

    private function validatedData(Request $request): array
    {
        $hexRule = ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'];
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'mode' => ['required', Rule::in(['normal', 'gradient'])],
            'primary_color' => $hexRule,
            'secondary_color' => $hexRule,
            'background_color' => $hexRule,
            'text_color' => $hexRule,
            'primary_gradient' => ['nullable', 'array'],
            'primary_gradient.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_gradient' => ['nullable', 'array'],
            'secondary_gradient.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'background_gradient' => ['nullable', 'array'],
            'background_gradient.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'text_gradient' => ['nullable', 'array'],
            'text_gradient.*' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        foreach (['primary_gradient', 'secondary_gradient', 'background_gradient', 'text_gradient'] as $field) {
            $validated[$field] = $this->normalizeColors($validated[$field] ?? []);
        }

        return $validated;
    }

    private function normalizeColors(array $colors): array
    {
        return array_values(array_filter($colors, fn ($color) => is_string($color) && trim($color) !== ''));
    }

    private function ensureThemeCompany(CompanyAppTheme $theme, Company $company): void
    {
        abort_if((int) $theme->company_id !== (int) $company->id, 404);
    }
}
