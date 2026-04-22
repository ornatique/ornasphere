<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JobWorker extends Model
{
    protected $fillable = [
        'company_id',
        'name',
        'email',
        'mobile_no',
        'address',
        'city',
        'area',
        'landmark',
        'pincode',
        'contact_person1_name',
        'contact_person1_phone',
        'contact_person2_name',
        'contact_person2_phone',
        'gst_no',
        'pan_no',
        'aadhaar_no',
        'birth_date',
        'anniversary_date',
        'reference',
        'remarks',
        'is_active',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'anniversary_date' => 'date',
        'is_active' => 'boolean',
    ];
}

