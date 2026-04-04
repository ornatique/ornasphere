<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    protected $fillable = [
        'company_id',
        'legacy_user_id',
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

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}

