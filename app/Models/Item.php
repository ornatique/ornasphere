<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Item extends Model
{
    use HasFactory;

    protected $fillable = [
        'company_id',
        'item_name',
        'item_code',
        'metal',
        'outward_carat',
        'inward_carat',
        'outward_purity',
        'inward_purity',
        'metal_formula',
        'issue_type',
        'jobwork_item_type',
        'labour_type',
        'labour_rate',
        'labour_unit',
        'tax_type',
        'hsn',
        'sac_code',
        'export_hsn',
        'auto_load_purity',
        'auto_create_label_purchase',
        'auto_create_label_config',
        'reuse',
        'numeric_length',
        'item_group',
        'remarks',
    ];

    public function labelConfig()
    {
        return $this->hasOne(LabelConfig::class);
    }
}
