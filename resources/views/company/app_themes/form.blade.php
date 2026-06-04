@extends('company_layout.admin')

@php
    $isEdit = $theme->exists;
    $action = $isEdit
        ? route('company.app-themes.update', [$company->slug, $theme->id])
        : route('company.app-themes.store', $company->slug);
    $gradientDefaults = [
        'primary_gradient' => $theme->primary_gradient ?: [$theme->primary_color ?: '#000000', '#333333'],
        'secondary_gradient' => $theme->secondary_gradient ?: [$theme->secondary_color ?: '#FFD700', '#FFA500'],
        'background_gradient' => $theme->background_gradient ?: [$theme->background_color ?: '#FFFFFF', '#F5F5F5'],
        'text_gradient' => $theme->text_gradient ?: [$theme->text_color ?: '#111111', '#222222'],
    ];
    $defaultThemeColors = [
        'primary_color' => '#000000',
        'secondary_color' => '#FFD700',
        'background_color' => '#FFFFFF',
        'text_color' => '#111111',
    ];
    $defaultThemeGradients = [
        'primary_gradient' => ['#000000', '#333333'],
        'secondary_gradient' => ['#FFD700', '#FFA500'],
        'background_gradient' => ['#FFFFFF', '#F5F5F5'],
        'text_gradient' => ['#111111', '#222222'],
    ];
@endphp

@section('content')
<div class="content-wrapper">
    <form action="{{ $action }}" method="POST">
        @csrf
        @if($isEdit)
            @method('PUT')
        @endif

        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="card-title mb-0">{{ $isEdit ? 'Edit App Theme' : 'Create App Theme' }}</h4>
                <a href="{{ route('company.app-themes.index', $company->slug) }}" class="btn btn-secondary">Back</a>
            </div>

            <div class="card-body">
                @if($errors->any())
                    <div class="alert alert-danger">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label>Theme Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $theme->name) }}" required>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label>Theme Type</label>
                        <select name="mode" id="themeMode" class="form-control" required>
                            <option value="normal" @selected(old('mode', $theme->mode) === 'normal')>Normal Color</option>
                            <option value="gradient" @selected(old('mode', $theme->mode) === 'gradient')>Gradient Color</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="is_active" value="1" id="is_active" class="form-check-input" @checked(old('is_active', $theme->is_active))>
                            <label for="is_active" class="form-check-label">Set as active app theme</label>
                        </div>
                    </div>
                </div>

                <div class="row">
                    @foreach([
                        'primary_color' => 'Primary (Appbar & Buttons)',
                        'secondary_color' => 'Highlight (Badges & Tags)',
                        'background_color' => 'Screen (Full Background)',
                        'text_color' => 'Typography (Text Content)',
                    ] as $field => $label)
                        <div class="col-md-3 mb-3">
                            <label>{{ $label }}</label>
                            <div class="d-flex gap-2">
                                <input type="color" class="form-control p-1 theme-color-picker" data-target="{{ $field }}" value="{{ old($field, $theme->{$field}) }}">
                                <input type="text" name="{{ $field }}" id="{{ $field }}" class="form-control theme-color-code" value="{{ old($field, $theme->{$field}) }}" required>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div id="gradientSection" class="mt-3">
                    <h5 class="mb-3">Gradient Colors</h5>
                    <div class="row">
                        @foreach([
                            'primary_gradient' => 'Primary Gradient',
                            'secondary_gradient' => 'Highlight Gradient',
                            'background_gradient' => 'Screen Gradient',
                            'text_gradient' => 'Typography Gradient',
                        ] as $field => $label)
                            <div class="col-md-3 mb-3">
                                <div class="theme-preview-card">
                                    <label>{{ $label }}</label>
                                    @foreach(array_values($gradientDefaults[$field]) as $index => $value)
                                        <div class="d-flex gap-2 mb-2">
                                            <input type="color" class="form-control p-1 gradient-picker" data-target="{{ $field }}_{{ $index }}" value="{{ old($field . '.' . $index, $value) }}">
                                            <input type="text" name="{{ $field }}[]" id="{{ $field }}_{{ $index }}" class="form-control gradient-code" value="{{ old($field . '.' . $index, $value) }}">
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <div class="mt-4">
                    <h5>Visual Preview</h5>
                    <div class="theme-preview-box">
                        <div class="preview-header">Dashboard Header</div>
                        <div class="preview-screen">
                            <span class="preview-badge">NEW</span>
                            <h4>This is a Theme Heading</h4>
                            <p>Body text style preview.</p>
                            <button type="button" class="preview-button">Save Changes</button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card-footer text-end">
                <button type="button" id="resetThemeDefaults" class="btn btn-warning me-2">Reset Default Colors</button>
                <button type="submit" class="btn btn-success">{{ $isEdit ? 'Update Theme' : 'Save Theme' }}</button>
            </div>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .theme-preview-card {
        border: 1px solid #3b3f5c;
        border-radius: 10px;
        padding: 15px;
        height: 100%;
    }

    .theme-preview-box {
        border: 1px solid #3b3f5c;
        border-radius: 12px;
        overflow: hidden;
        max-width: 520px;
    }

    .preview-header {
        padding: 16px;
        font-weight: 700;
    }

    .preview-screen {
        padding: 20px;
        min-height: 180px;
    }

    .preview-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-weight: 700;
        margin-bottom: 12px;
    }

    .preview-button {
        border: 0;
        border-radius: 6px;
        padding: 10px 18px;
        font-weight: 700;
    }
</style>
@endpush

@push('scripts')
<script>
    const defaultThemeColors = @json($defaultThemeColors);
    const defaultThemeGradients = @json($defaultThemeGradients);

    function currentGradient(field) {
        return Array.from(document.querySelectorAll('input[name="' + field + '[]"]'))
            .map(input => input.value)
            .filter(Boolean);
    }

    function applyPreview() {
        const mode = document.getElementById('themeMode').value;
        const primary = document.getElementById('primary_color').value;
        const secondary = document.getElementById('secondary_color').value;
        const background = document.getElementById('background_color').value;
        const text = document.getElementById('text_color').value;

        const primaryBg = mode === 'gradient'
            ? 'linear-gradient(135deg, ' + currentGradient('primary_gradient').join(', ') + ')'
            : primary;
        const secondaryBg = mode === 'gradient'
            ? 'linear-gradient(135deg, ' + currentGradient('secondary_gradient').join(', ') + ')'
            : secondary;
        const screenBg = mode === 'gradient'
            ? 'linear-gradient(135deg, ' + currentGradient('background_gradient').join(', ') + ')'
            : background;

        document.querySelector('.preview-header').style.background = primaryBg;
        document.querySelector('.preview-header').style.color = text;
        document.querySelector('.preview-screen').style.background = screenBg;
        document.querySelector('.preview-screen').style.color = text;
        document.querySelector('.preview-badge').style.background = secondaryBg;
        document.querySelector('.preview-badge').style.color = text;
        document.querySelector('.preview-button').style.background = primaryBg;
        document.querySelector('.preview-button').style.color = text;
    }

    function toggleMode() {
        document.getElementById('gradientSection').style.display =
            document.getElementById('themeMode').value === 'gradient' ? 'block' : 'none';
        applyPreview();
    }

    document.addEventListener('input', function (event) {
        if (event.target.classList.contains('theme-color-picker')) {
            document.getElementById(event.target.dataset.target).value = event.target.value.toUpperCase();
        }

        if (event.target.classList.contains('theme-color-code')) {
            const picker = document.querySelector('.theme-color-picker[data-target="' + event.target.id + '"]');
            if (picker && /^#[0-9A-Fa-f]{6}$/.test(event.target.value)) {
                picker.value = event.target.value;
            }
        }

        if (event.target.classList.contains('gradient-picker')) {
            document.getElementById(event.target.dataset.target).value = event.target.value.toUpperCase();
        }

        if (event.target.classList.contains('gradient-code')) {
            const picker = document.querySelector('.gradient-picker[data-target="' + event.target.id + '"]');
            if (picker && /^#[0-9A-Fa-f]{6}$/.test(event.target.value)) {
                picker.value = event.target.value;
            }
        }

        applyPreview();
    });

    document.getElementById('themeMode').addEventListener('change', toggleMode);
    document.getElementById('resetThemeDefaults').addEventListener('click', function () {
        Object.entries(defaultThemeColors).forEach(function ([field, value]) {
            const codeInput = document.getElementById(field);
            const picker = document.querySelector('.theme-color-picker[data-target="' + field + '"]');

            if (codeInput) {
                codeInput.value = value;
            }

            if (picker) {
                picker.value = value;
            }
        });

        Object.entries(defaultThemeGradients).forEach(function ([field, values]) {
            values.forEach(function (value, index) {
                const inputId = field + '_' + index;
                const codeInput = document.getElementById(inputId);
                const picker = document.querySelector('.gradient-picker[data-target="' + inputId + '"]');

                if (codeInput) {
                    codeInput.value = value;
                }

                if (picker) {
                    picker.value = value;
                }
            });
        });

        document.getElementById('themeMode').value = 'normal';
        toggleMode();
    });
    toggleMode();
</script>
@endpush
