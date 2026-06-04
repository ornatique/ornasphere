<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CompanyAppTheme extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'mode',
        'is_active',
        'primary_color',
        'secondary_color',
        'background_color',
        'text_color',
        'primary_gradient',
        'secondary_gradient',
        'background_gradient',
        'text_gradient',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'primary_gradient' => 'array',
        'secondary_gradient' => 'array',
        'background_gradient' => 'array',
        'text_gradient' => 'array',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public function toAppPayload(): array
    {
        $normal = [
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'background_color' => $this->background_color,
            'text_color' => $this->text_color,
        ];

        $gradient = [
            'primary' => $this->cleanGradient($this->primary_gradient, $this->primary_color),
            'secondary' => $this->cleanGradient($this->secondary_gradient, $this->secondary_color),
            'background' => $this->cleanGradient($this->background_gradient, $this->background_color),
            'text' => $this->cleanGradient($this->text_gradient, $this->text_color),
        ];

        return [
            'id' => (int) $this->id,
            'name' => $this->name,
            'mode' => $this->mode,
            'is_active' => (bool) $this->is_active,
            'is_gradient' => $this->mode === 'gradient',
            'colors' => $normal,
            'gradient' => $gradient,
            'primary' => [
                'color' => $this->primary_color,
                'colors' => $gradient['primary'],
            ],
            'secondary' => [
                'color' => $this->secondary_color,
                'colors' => $gradient['secondary'],
            ],
            'background' => [
                'color' => $this->background_color,
                'colors' => $gradient['background'],
            ],
            'text' => [
                'color' => $this->text_color,
                'colors' => $gradient['text'],
            ],
        ];
    }

    private function cleanGradient($colors, string $fallback): array
    {
        $colors = is_array($colors) ? $colors : [];
        $colors = array_values(array_filter($colors, fn ($color) => is_string($color) && trim($color) !== ''));

        return $colors ?: [$fallback];
    }

    public static function defaultPayload(): array
    {
        return [
            'id' => null,
            'name' => 'Default Theme',
            'mode' => 'normal',
            'is_active' => true,
            'is_gradient' => false,
            'colors' => [
                'primary_color' => '#000000',
                'secondary_color' => '#FFD700',
                'background_color' => '#FFFFFF',
                'text_color' => '#111111',
            ],
            'gradient' => [
                'primary' => ['#000000', '#333333'],
                'secondary' => ['#FFD700', '#FFA500'],
                'background' => ['#FFFFFF', '#F5F5F5'],
                'text' => ['#111111', '#222222'],
            ],
            'primary' => ['color' => '#000000', 'colors' => ['#000000', '#333333']],
            'secondary' => ['color' => '#FFD700', 'colors' => ['#FFD700', '#FFA500']],
            'background' => ['color' => '#FFFFFF', 'colors' => ['#FFFFFF', '#F5F5F5']],
            'text' => ['color' => '#111111', 'colors' => ['#111111', '#222222']],
        ];
    }
}
