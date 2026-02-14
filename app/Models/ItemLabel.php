<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ItemLabel extends Model
{
    protected $fillable = [

        'company_id',
        'item_id',
        'label_config_id',
        'qr_code',
        'barcode',
        'serial_no',
        'is_printed'

    ];

    public function item()
    {
        return $this->belongsTo(Item::class);
    }
}
