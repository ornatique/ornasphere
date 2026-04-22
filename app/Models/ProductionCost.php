<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductionCost extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];
}

