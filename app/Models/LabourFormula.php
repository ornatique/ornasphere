<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LabourFormula extends Model
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

