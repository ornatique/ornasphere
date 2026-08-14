<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryPerson extends Model
{
    public const DEFAULT_CUSTOMER = 'Customer';
    public const DEFAULT_WORKER = 'Worker';

    protected $fillable = [
        'company_id',
        'category_name',
        'is_system_default',
    ];

    protected $casts = [
        'company_id' => 'integer',
        'is_system_default' => 'boolean',
    ];

    public function company()
    {
        return $this->belongsTo(Company::class);
    }

    public static function systemDefaultNames(): array
    {
        return [
            self::DEFAULT_CUSTOMER,
            self::DEFAULT_WORKER,
        ];
    }

    public static function ensureCompanyDefaults(int $companyId): void
    {
        foreach (self::systemDefaultNames() as $categoryName) {
            $category = self::where('company_id', $companyId)
                ->whereRaw('LOWER(TRIM(category_name)) = ?', [strtolower($categoryName)])
                ->first();

            if ($category) {
                $category->update([
                    'category_name' => $categoryName,
                    'is_system_default' => true,
                ]);

                continue;
            }

            self::create([
                'company_id' => $companyId,
                'category_name' => $categoryName,
                'is_system_default' => true,
            ]);
        }
    }

    public function isSystemDefault(): bool
    {
        return (bool) $this->is_system_default;
    }
}
