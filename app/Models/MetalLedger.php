<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MetalLedger extends Model
{
    protected $table = 'metal_ledgers';

    protected $fillable =
    [
        'sale_id',
        'customer_id',
        'metal_type',
        'weight',
        'type',
    ];
}
