<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitingCard extends Model
{
    protected $fillable = [
        'company_id',
        'uploaded_by',
        'image_path',
        'name',
        'mobile_no',
        'mobile_numbers',
        'email',
        'address',
        'city',
        'pincode',
        'original_language',
        'original_text',
        'english_text',
        'raw_payload',
    ];

    protected $casts = [
        'raw_payload' => 'array',
        'mobile_numbers' => 'array',
    ];
}
