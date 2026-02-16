<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemSet extends Model
{

    protected $fillable = [

        'company_id',
        'item_id',

        'gross_weight',
        'other',
        'net_weight',

        'sale_labour_formula',
        'sale_labour_rate',
        'sale_labour_amount',

        'sale_other',

        'supplier_person',
        'size',
        'HUID'

    ];


    public function item()
    {
        return $this->belongsTo(Item::class);
    }

}

