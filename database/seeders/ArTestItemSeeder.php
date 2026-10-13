<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Item;
use Illuminate\Database\Seeder;

class ArTestItemSeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::query()->orderBy('id')->first();

        if (!$company) {
            $this->command?->warn('No company found. Skipping AR test item seed.');
            return;
        }

        Item::query()->updateOrCreate(
            [
                'company_id' => $company->id,
                'item_code' => 'AR-TEST-001',
            ],
            [
                'item_name' => 'AR Test Ring Demo',
                'metal' => 'Gold',
                'outward_carat' => 22,
                'inward_carat' => 22,
                'outward_purity' => 91.60,
                'inward_purity' => 91.60,
                'metal_formula' => 'per_netweight',
                'labour_type' => 'per_quantity',
                'labour_unit' => 'per_quantity',
                'jobwork_item_type' => 'finish',
                'hsn' => '7113',
                'export_hsn' => '7113',
                'item_group' => 'finish',
                'remarks' => 'Default AR test data seeded by system.',
                'auto_load_purity' => true,
                'auto_create_label_purchase' => false,
                'auto_create_label_config' => false,
                'jewellery_type' => 'ring',
                'ar_mode' => 'hand_tryon',
                'glb_url' => 'https://modelviewer.dev/shared-assets/models/Astronaut.glb',
                'usdz_url' => 'https://modelviewer.dev/shared-assets/models/Astronaut.usdz',
                'thumbnail_url' => 'https://modelviewer.dev/assets/poster-astronaut.png',
                'deepar_effect_id' => 'demo/ring-effect',
                'ar_meta' => json_encode([
                    'scale' => 1,
                    'offset' => ['x' => 0, 'y' => 0, 'z' => 0],
                    'rotation' => ['x' => 0, 'y' => 0, 'z' => 0],
                ]),
            ]
        );

        $this->command?->info('AR test item seeded/updated successfully.');
    }
}
