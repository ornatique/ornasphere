<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OtherCharge extends Model
{
    protected $casts = [
        'default_amount' => 'float',
        'default_weight' => 'float',
        'quantity_pcs' => 'integer',
        'weight_percent' => 'float',
        'sale_weight_percent' => 'float',
        'purchase_weight_percent' => 'float',
        'sequence_no' => 'integer',
        'is_default' => 'boolean',
        'is_selected' => 'boolean',
        'other_charge_ol' => 'boolean',
        'purity' => 'float',
        'required_purity' => 'float',
        'carat_weight_auto_conversion' => 'boolean',
        'diamond' => 'boolean',
        'stone' => 'boolean',
        'stock_effect' => 'boolean',
        'party_account_effect' => 'boolean',
        'item_id' => 'integer',
    ];

    protected $fillable = [

        'company_id',
        'other_charge',
        'code',

        'default_amount',
        'default_weight',
        'quantity_pcs',

        'weight_formula',
        'weight_percent',
        'sale_weight_percent',
        'purchase_weight_percent',

        'other_amt_formula',

        'sequence_no',

        'is_default',
        'is_selected',
        'other_charge_ol',

        'purity',
        'required_purity',

        'merge_other_charge',
        'wt_operation',

        'carat_weight_auto_conversion',
        'diamond',
        'stone',
        'stock_effect',
        'party_account_effect',

        'item_id',

        'remarks'
    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

}
